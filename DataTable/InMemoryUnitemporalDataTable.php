<?php

namespace ThomasInstitut\DataTable;


use ArrayIterator;
use Iterator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use ThomasInstitut\DataTable\Exception\InvalidRowForUpdate;
use ThomasInstitut\DataTable\Exception\InvalidRowUpdateTime;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\Exception\RowDoesNotExist;
use ThomasInstitut\DataTable\IdGenerator\IdGenerator;
use ThomasInstitut\DataTable\IdGenerator\SequentialIdGenerator;
use ThomasInstitut\DataTable\ResultsIterator\ArrayResultsIterator;
use ThomasInstitut\DataTable\ResultsIterator\ResultsIterator;
use ThomasInstitut\TimeString\TimeString;
use Traversable;

class InMemoryUnitemporalDataTable implements UnitemporalDataTable
{


    const string InternalRowIdKey = '__rowId__';
    private string $tableName = '';
    protected LoggerInterface $logger;
    protected IdGenerator $idGenerator;
    protected string $idColumnName = 'id';

    protected string $validFromColumn = UnitemporalDataTable::DEFAULT_VALID_FROM_COLUMN;
    protected string $validUntilColumn = UnitemporalDataTable::DEFAULT_VALID_UNTIL_COLUMN;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $theData;

    /**
     *
     * @var string
     */
    private string $errorMessage;

    /**
     *
     * @var int
     */
    private int $errorCode;


    public function __construct(?array &$data = null, ?IdGenerator $idGenerator = null, ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
        if ($data === null) {
            $this->theData = [];
        } else {
            $this->theData = &$data;
        }
        $this->idGenerator = $idGenerator ?? new SequentialIdGenerator();
        $this->resetError();
    }

    /**
     * @param array<int, array<string, mixed>> $theRow
     * @return void
     */
    private function internalAddRow(array $theRow): void
    {
        $newInternalId = count($this->theData);
        $theRow[self::InternalRowIdKey] = $newInternalId;
        $this->theData[$newInternalId] = $theRow;
//        print "Add row to internal data: $newInternalId : {$theRow[$this->idColumnName]}, from {$theRow[$this->validFromColumn]} until {$theRow[$this->validUntilColumn]}\n";
    }

    private function sanitizedRow(?array $row, bool $stripTimeInfo = false): array|null
    {
        if ($row === null) {
            return null;
        }
        unset($row[self::InternalRowIdKey]);
        if ($stripTimeInfo) {
            unset($row[$this->validFromColumn]);
            unset($row[$this->validUntilColumn]);
        }
        return $row;
    }

    private function internalMarkRowAsInvalid(int $internalRowId, string $timeString): void
    {
        $theRow = $this->theData[$internalRowId];
//        print "Marking internal row $internalRowId ID: {$theRow[$this->idColumnName]} as invalid at time: $timeString\n";
        $this->theData[$internalRowId][$this->validUntilColumn] = $timeString;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function sanitizedRowSet(array $rows, bool $stripTimeInfo = false): array
    {
        return array_values(array_map(function ($row) use ($stripTimeInfo) {
            return $this->sanitizedRow($row, $stripTimeInfo);
        }, $rows));
    }


    /**
     * @throws RowDoesNotExist
     */
    private function internalGetRowHistory(int $rowId): array
    {

        $data = array_filter($this->theData, function ($row) use ($rowId) {
            return $row[$this->idColumnName] === $rowId;
        });

        if (count($data) === 0) {
            throw new RowDoesNotExist("Row $rowId does not exist");
        }

        // return sorted by valid from time
        usort($data, function ($a, $b) {
            return strcmp($a[$this->validFromColumn], $b[$this->validFromColumn]);
        });

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function createRow(array $theRow): int
    {
        try {
            return $this->createRowWithTime($theRow, TimeString::now());
        } catch (Exception\InvalidTimeStringException) {
            throw new RuntimeException('Unexpected error creating row');
        }
    }

    public function deleteRow(int $rowId): int
    {
        try {
            return $this->deleteRowWithTime($rowId, TimeString::now());
        } catch (Exception\InvalidTimeStringException|InvalidRowUpdateTime) {
            throw new RuntimeException('Unexpected error deleting row');
        }
    }

    private function resetError(): void
    {
        $this->errorCode = DataTable::ERROR_NO_ERROR;
        $this->errorMessage = "";
    }

    public function createRowWithTime(array $theRow, string $timeString): int
    {
        $this->resetError();
        $newId = null;
        if (isset($theRow[$this->idColumnName])) {
            $newId = $theRow[$this->idColumnName];
            if (is_int($newId)) {
                if ($this->rowExistsWithTime($theRow[$this->idColumnName], $timeString)) {
                    $this->setErrorCode(DataTable::ERROR_ROW_ALREADY_EXISTS);
                    $this->setErrorMessage("Row already exists");
                    throw new RowAlreadyExists("Row already exists");
                }
            } else {
                $newId = null;
            }
        }
        if ($newId === null) {
            $newId = $this->idGenerator->getOneUnusedId($this);
        }
        $theRow[$this->idColumnName] = $newId;
        $theRow[$this->validFromColumn] = $timeString;
        $theRow[$this->validUntilColumn] = TimeString::END_OF_TIMES;
        $this->internalAddRow($theRow);
        return $theRow[$this->idColumnName];
    }

    public function rowExistsWithTime(int $rowId, string $timeString): bool
    {
        return $this->getRowWithTime($rowId, $timeString) !== null;
    }

    public function getRowWithTime(int $rowId, string $timeString): ?array
    {
        try {
            $data = $this->getRowHistory($rowId);
            $valid = $this->getDataRowsValidAtTime($data, $timeString);
            if (count($valid) === 0) {
                return null;
            }

            if (count($valid) > 1) {
                throw new \LogicException("Found more than 1 valid row for a given time");
            }
            return $this->sanitizedRow($valid[0]);
        } catch (RowDoesNotExist) {
            return null;
        }
    }

    public function findRowsWithTime($theRow, $maxResults, string $timeString): ResultsIterator
    {
        $validRows = $this->getDataRowsValidAtTime($this->theData, $timeString);
        $foundRows = [];

        foreach ($validRows as $row) {
            if ($this->rowMatches($row, $theRow)) {
                $foundRows[] = $row;
            }
        }
        return new ArrayResultsIterator($this->sanitizedRowSet($foundRows));
    }

    private function rowMatches(array $theRow, array $rowToMatch): bool
    {
        foreach ($rowToMatch as $key => $value) {
            if (!isset($theRow[$key]) || $theRow[$key] !== $value) {
                return false;
            }
        }
        return true;
    }

    public function searchWithTime(array $searchSpecArray, int $searchType, string $timeString, int $maxResults = 0): ResultsIterator
    {
        // TODO: Implement searchWithTime() method.
        return new ArrayResultsIterator([]);
    }

    private function setError(string $message, int $errorCode): void
    {
        $this->errorMessage = $message;
        $this->errorCode = $errorCode;
    }

    protected function isRowIdGoodForRowUpdate(array $theRow, string $context): bool
    {
        if (!isset($theRow[$this->idColumnName])) {
            $this->setError('Id not set in given row' . " ($context)", self::ERROR_ID_NOT_SET);
            return false;
        }

        if ($theRow[$this->idColumnName] <= 0) {
            $this->setError('Id is equal to zero in given row' . " ($context)", self::ERROR_ID_IS_ZERO);
            return false;
        }
        if (!is_int($theRow[$this->idColumnName])) {
            $this->setError('Id in given row is not an integer' . " ($context)", self::ERROR_ID_NOT_INTEGER);
            return false;
        }
        return true;
    }

    public function updateRowWithTime(array $theRow, string $timeString): void
    {
        if (!$this->isRowIdGoodForRowUpdate($theRow, 'updateRowWithTime')) {
            throw new InvalidRowForUpdate($this->errorMessage);
        }
        try {
            $data = $this->internalGetRowHistory($theRow[$this->idColumnName]);
            $latestRow = $data[count($data) - 1];
            if ($timeString <= $latestRow[$this->validFromColumn]) {
                throw new InvalidRowUpdateTime("The given time is not later than the last version of the row");
            }
            $this->internalMarkRowAsInvalid($latestRow[self::InternalRowIdKey], $timeString);
            $updatedRow = $latestRow;
            foreach ($theRow as $columnName => $columnValue) {
                if ($columnName !== $this->idColumnName) {
                    $updatedRow[$columnName] = $columnValue;
                }
            }
            $updatedRow[$this->validFromColumn] = $timeString;
            $updatedRow[$this->validUntilColumn] = TimeString::END_OF_TIMES;
            $this->internalAddRow($updatedRow);
        } catch (RowDoesNotExist) {
            $this->setError("The given row does not exist", DataTable::ERROR_ROW_DOES_NOT_EXIST);
            throw new InvalidRowForUpdate("The given row does not exist");
        }
    }

    public function deleteRowWithTime(int $rowId, string $timeString): int
    {
        try {
            $data = $this->internalGetRowHistory($rowId);
            $latestRow = $data[count($data) - 1];
            if ($timeString <= $latestRow[$this->validFromColumn]) {
                throw new InvalidRowUpdateTime("The given time is not later than the last version of the row");
            }
            $this->internalMarkRowAsInvalid($latestRow[self::InternalRowIdKey], $timeString);
            return 1;
        } catch (RowDoesNotExist) {
            return 0;
        }
    }


    public function getRowHistory(int $rowId): array
    {
        return $this->sanitizedRowSet($this->internalGetRowHistory($rowId));
    }

    public function getIterator(): Traversable
    {
        return $this->getAllRows();
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->rowExists(intval($offset));
    }

    public function offsetGet(mixed $offset): ?array
    {
        return $this->getRow(intval($offset));
    }

    /**
     * @throws RowAlreadyExists
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {

        if ($offset === null) {
            $this->createRow($value);
            return;
        }
        $id = intval($offset);
        $value[$this->idColumnName] = $id;
        if ($this->rowExists($id)) {
            try {
                $this->updateRow($value);
            } catch (Exception\InvalidRowForUpdate) {
            }
        } else {
            try {
                $this->createRow($value);
            } catch (RowAlreadyExists) { // @codeCoverageIgnore
                // this should never happen
            }

        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->deleteRow(intval($offset));
    }

    public function setIdGenerator(IdGenerator $ig): void
    {
        $this->idGenerator = $ig;
    }

    public function rowExists(int $rowId): bool
    {
        return $this->rowExistsWithTime($rowId, TimeString::now());
    }

    public function getRow(int $rowId): ?array
    {
        return $this->sanitizedRow($this->getRowWithTime($rowId, TimeString::now()), true);
    }


    public function getAllRows(): ResultsIterator
    {
        return new ArrayResultsIterator($this->sanitizedRowSet($this->getDataRowsValidAtTime($this->theData, TimeString::now())));
    }

    public function getDataRowsValidAtTime(array $data, string $time): array
    {
        return array_values(array_filter($data, function ($row) use ($time) {
            return $row[$this->validFromColumn] <= $time && $row[$this->validUntilColumn] > $time;
        }));
    }

    function findRows(array $rowToMatch, int $maxResults = 0): ResultsIterator
    {
        return $this->findRowsWithTime($rowToMatch, $maxResults, TimeString::now());
    }

    public function search(array $searchSpecArray, int $searchType = self::SEARCH_AND, int $maxResults = 0): ResultsIterator
    {
        // TODO: Implement search() method.
        return new ArrayResultsIterator([]);
    }

    public function updateRow(array $theRow): void
    {
        try {
            $this->updateRowWithTime($theRow, TimeString::now());
        } catch (InvalidRowUpdateTime|Exception\InvalidTimeStringException $e) {
            throw new RuntimeException('Unexpected error updating row', $e);
        }
    }

    public function supportsTransactions(): bool
    {
        return false;
    }

    public function startTransaction(): bool
    {
        return false;
    }

    public function commit(): bool
    {
        return false;
    }

    public function rollBack(): bool
    {
        return false;
    }

    public function isInTransaction(): bool
    {
        return false;
    }

    public function isUnderlyingDatabaseInTransaction(): bool
    {
        return false;
    }

    public function getIdForKeyValue(string $key, mixed $value): int
    {
        // TODO: Implement getIdForKeyValue() method.
        return -1;
    }

    public function getMaxValueInColumn(string $columnName): int
    {
        return max(array_column($this->theData, $columnName));
    }

    public function getMaxId(): int
    {
        if (count($this->theData) === 0) {
            return 0;
        }
        return max(array_column($this->theData, $this->idColumnName));
    }

    public function getUniqueIds(): Iterator
    {
        return $this->getUniqueIdsWithTime(TimeString::now());
    }


    public function getUniqueIdsWithTime(string $timeString): Iterator
    {
        $ids = [];
        foreach ($this->theData as $row) {
            if ($row[$this->validFromColumn] <= $timeString && $row[$this->validUntilColumn] >= $timeString) {
                $ids[] = $row[$this->idColumnName];
            }
        }
        return new ArrayIterator(array_unique($ids));
    }

    public function getName(): string
    {
        return $this->tableName;
    }

    public function setName(string $name): void
    {
        $this->tableName = $name;
    }

    public function setIdColumnName(string $columnName): void
    {
        $this->idColumnName = $columnName;
    }

    public function getIdColumnName(): string
    {
        return $this->idColumnName;
    }

    /**
     * @param string $message
     */
    private function setErrorMessage(string $message): void
    {
        $this->errorMessage = $message;
    }

    /**
     * @param int $code
     */
    private function setErrorCode(int $code): void
    {
        $this->errorCode = $code;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }
}