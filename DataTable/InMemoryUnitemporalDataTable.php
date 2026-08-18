<?php

namespace ThomasInstitut\DataTable;


use ArrayIterator;
use Iterator;
use LogicException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use ThomasInstitut\DataTable\Exception\InvalidRowForUpdate;
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\DataTable\Exception\InvalidRowUpdateTime;
use ThomasInstitut\DataTable\Exception\InvalidSearchSpec;
use ThomasInstitut\DataTable\Exception\InvalidSearchType;
use ThomasInstitut\DataTable\Exception\InvalidTimeStringException;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\Exception\RowDoesNotExist;
use ThomasInstitut\DataTable\IdGenerator\IdGenerator;
use ThomasInstitut\DataTable\IdGenerator\SequentialIdGenerator;
use ThomasInstitut\DataTable\ResultsIterator\ArrayResultsIterator;
use ThomasInstitut\DataTable\ResultsIterator\ResultsIterator;
use ThomasInstitut\DataTable\UnitemporalConsistency\UnitemporalConsistencyChecker;
use ThomasInstitut\TimeString\TimeString;
use ThomasInstitut\TimeString\InvalidTimeZoneException;
use ThomasInstitut\TimeString\MalformedStringException;
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

    private string $errorMessage;

    private int $errorCode;


    /**
     * @param array<int, array<string, mixed>>|null $data the array to use to store the rows, if null a new array will be created
     * @throws InvalidArgumentException
     */
    public function __construct(
        ?array &$data = null,
        ?IdGenerator $idGenerator = null,
        ?LoggerInterface $logger = null,
        string $validFromColumnName = UnitemporalDataTable::DEFAULT_VALID_FROM_COLUMN,
        string $validUntilColumnName = UnitemporalDataTable::DEFAULT_VALID_UNTIL_COLUMN
    )
    {
        $this->setValidFromColumnName($validFromColumnName);
        $this->setValidUntilColumnName($validUntilColumnName);
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
     */
    private function internalAddRow(array $theRow): void
    {
        $newInternalId = count($this->theData);
        $theRow[self::InternalRowIdKey] = $newInternalId;
        $this->theData[$newInternalId] = $theRow;
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
        $this->theData[$internalRowId][$this->validUntilColumn] = $timeString;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function sanitizedRowSet(array $rows, bool $stripTimeInfo = false): array
    {
        return array_values(array_map(fn(array $row): ?array => $this->sanitizedRow($row, $stripTimeInfo), $rows));
    }


    /**
     * @throws RowDoesNotExist
     */
    private function internalGetRowHistory(int $rowId): array
    {

        $data = array_filter($this->theData, fn(array $row): bool => $row[$this->idColumnName] === $rowId);

        if (count($data) === 0) {
            throw new RowDoesNotExist("Row $rowId does not exist");
        }

        // return sorted by valid from time
        usort($data, fn(array $a, array $b): int => strcmp((string) $a[$this->validFromColumn], (string) $b[$this->validFromColumn]));

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
        $timeString = $this->getValidTimeString($timeString, 'createRowWithTime');
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
        $timeString = $this->getValidTimeString($timeString, 'getRowWithTime');
        try {
            $data = $this->getRowHistory($rowId);
            $valid = $this->getDataRowsValidAtTime($data, $timeString);
            if (count($valid) === 0) {
                return null;
            }

            if (count($valid) > 1) {
                throw new LogicException("Found more than 1 valid row for a given time");
            }
            return $this->sanitizedRow($valid[0]);
        } catch (RowDoesNotExist) {
            return null;
        }
    }

    public function findRowsWithTime($theRow, $maxResults, string $timeString): ResultsIterator
    {
        $timeString = $this->getValidTimeString($timeString, 'findRowsWithTime');
        // Match PdoUnitemporalDataTable, whose SQL query cannot represent an
        // empty row predicate and therefore returns no rows for this input.
        if ($theRow === []) {
            return new ArrayResultsIterator([]);
        }
        $validRows = $this->getDataRowsValidAtTime($this->theData, $timeString);
        $foundRows = [];

        foreach ($validRows as $row) {
            if ($this->rowMatches($row, $theRow)) {
                $foundRows[] = $row;
                if ($maxResults > 0 && count($foundRows) === $maxResults) {
                    break;
                }
            }
        }
        return new ArrayResultsIterator($this->sanitizedRowSet($foundRows));
    }

    private function rowMatches(array $theRow, array $rowToMatch): bool
    {
        foreach ($rowToMatch as $key => $value) {
            if (!array_key_exists($key, $theRow) || $theRow[$key] !== $value) {
                return false;
            }
        }
        return true;
    }

    public function searchWithTime(array $searchSpecArray, int $searchType, string $timeString, int $maxResults = 0): ResultsIterator
    {
        $this->checkSearchSpec($searchSpecArray, $searchType);
        $timeString = $this->getValidTimeString($timeString, 'searchWithTime');
        $validRows = $this->getDataRowsValidAtTime($this->theData, $timeString);
        $foundRows = [];

        foreach ($validRows as $row) {
            if ($this->rowMatchesSearchSpec($row, $searchSpecArray, $searchType)) {
                $foundRows[] = $row;
                if ($maxResults > 0 && count($foundRows) === $maxResults) {
                    break;
                }
            }
        }

        return new ArrayResultsIterator($this->sanitizedRowSet($foundRows));
    }

    /**
     * @throws InvalidSearchSpec
     * @throws InvalidSearchType
     */
    private function checkSearchSpec(array $searchSpecArray, int $searchType): void
    {
        $this->resetError();

        if ($searchSpecArray === []) {
            $this->setError('searchSpec is not valid', self::ERROR_INVALID_SPEC_ARRAY);
            throw new InvalidSearchSpec($this->errorMessage, $this->errorCode);
        }

        foreach ($searchSpecArray as $spec) {
            if (!isset($spec[self::SEARCH_SPEC_COLUMN]) || !is_string($spec[self::SEARCH_SPEC_COLUMN])) {
                $this->setError('searchSpec is not valid', self::ERROR_INVALID_SPEC_ARRAY);
                throw new InvalidSearchSpec($this->errorMessage, $this->errorCode);
            }
            if (!array_key_exists(self::SEARCH_SPEC_VALUE, $spec)) {
                $this->setError('searchSpec is not valid', self::ERROR_INVALID_SPEC_ARRAY);
                throw new InvalidSearchSpec($this->errorMessage, $this->errorCode);
            }
            if (!isset($spec[self::SEARCH_SPEC_CONDITION]) || !is_int($spec[self::SEARCH_SPEC_CONDITION]) ||
                !in_array($spec[self::SEARCH_SPEC_CONDITION], [
                    self::COND_EQUAL_TO,
                    self::COND_NOT_EQUAL_TO,
                    self::COND_LESS_THAN,
                    self::COND_LESS_OR_EQUAL_TO,
                    self::COND_GREATER_THAN,
                    self::COND_GREATER_OR_EQUAL_TO,
                ], true)) {
                $this->setError('searchSpec is not valid', self::ERROR_INVALID_SPEC_ARRAY);
                throw new InvalidSearchSpec($this->errorMessage, $this->errorCode);
            }
        }

        if ($searchType !== self::SEARCH_AND && $searchType !== self::SEARCH_OR) {
            $this->setError('Invalid search type', self::ERROR_INVALID_SEARCH_TYPE);
            throw new InvalidSearchType($this->errorMessage, $this->errorCode);
        }
    }

    private function rowMatchesSearchSpec(array $dataRow, array $searchSpecArray, int $searchType): bool
    {
        if ($searchType === self::SEARCH_AND) {
            foreach ($searchSpecArray as $spec) {
                if (!$this->rowMatchesSearchCondition($dataRow, $spec)) {
                    return false;
                }
            }
            return true;
        }

        foreach ($searchSpecArray as $spec) {
            if ($this->rowMatchesSearchCondition($dataRow, $spec)) {
                return true;
            }
        }
        return false;
    }

    private function rowMatchesSearchCondition(array $dataRow, array $spec): bool
    {
        $column = $spec[self::SEARCH_SPEC_COLUMN];
        if (!array_key_exists($column, $dataRow)) {
            return false;
        }

        $rowValue = $dataRow[$column];
        $value = $spec[self::SEARCH_SPEC_VALUE];
        $comparison = is_string($value) ? strcmp((string) $rowValue, $value) : $rowValue <=> $value;

        return match ($spec[self::SEARCH_SPEC_CONDITION]) {
            self::COND_EQUAL_TO => is_string($value) ? $comparison === 0 : $rowValue === $value,
            self::COND_NOT_EQUAL_TO => is_string($value) ? $comparison !== 0 : $rowValue !== $value,
            self::COND_LESS_THAN => $comparison < 0,
            self::COND_LESS_OR_EQUAL_TO => $comparison <= 0,
            self::COND_GREATER_THAN => $comparison > 0,
            self::COND_GREATER_OR_EQUAL_TO => $comparison >= 0,
            default => throw new LogicException('Invalid search condition'),
        };
    }

    private function setError(string $message, int $errorCode): void
    {
        $this->errorMessage = $message;
        $this->errorCode = $errorCode;
    }

    /**
     * @throws InvalidTimeStringException
     */
    private function getValidTimeString(string $timeString, string $context): string
    {
        try {
            return TimeString::fromString($timeString);
        } catch (InvalidTimeZoneException|MalformedStringException) {
            $this->setError("Invalid time given for $context : \"$timeString\"", self::ERROR_INVALID_TIME);
            throw new InvalidTimeStringException($this->errorMessage, $this->errorCode);
        }
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
        $timeString = $this->getValidTimeString($timeString, 'updateRowWithTime');
        if (!$this->isRowIdGoodForRowUpdate($theRow, 'updateRowWithTime')) {
            throw new InvalidRowForUpdate($this->errorMessage);
        }
        try {
            $data = $this->internalGetRowHistory($theRow[$this->idColumnName]);
            $latestRow = $data[count($data) - 1];
            if ($timeString <= $latestRow[$this->validFromColumn]) {
                $this->setError('The given time is not later than the last version of the row', UnitemporalDataTable::ERROR_INVALID_ROW_UPDATE_TIME);
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
        $timeString = $this->getValidTimeString($timeString, 'deleteRowWithTime');
        try {
            $data = $this->internalGetRowHistory($rowId);
            $latestRow = $data[count($data) - 1];
            if ($timeString <= $latestRow[$this->validFromColumn]) {
                $this->setError('The given time is not later than the last version of the row', UnitemporalDataTable::ERROR_INVALID_ROW_UPDATE_TIME);
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
        try {
            return $this->sanitizedRowSet($this->internalGetRowHistory($rowId));
        } catch (RowDoesNotExist $e) {
            $this->setError("The row with id $rowId has never existed", DataTable::ERROR_ROW_DOES_NOT_EXIST);
            throw $e;
        }
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
        try {
            return $this->rowExistsWithTime($rowId, TimeString::now());
        } catch (Exception\InvalidTimeStringException $e) {
            throw new RuntimeException('Unexpected error checking row existence', $e->getCode(), $e);
        }
    }

    public function getRow(int $rowId): ?array
    {
        $this->resetError();
        try {
            $row = $this->getRowWithTime($rowId, TimeString::now());
        } catch (InvalidTimeStringException $e) {
            throw new RuntimeException('Unexpected error getting row',  $e->getCode(), $e);
        }
        if ($row === null) {
            $this->setError("The row with id $rowId does not exist", self::ERROR_ROW_DOES_NOT_EXIST);
            return null;
        }
        return $this->sanitizedRow($row, true);
    }


    public function getAllRows(): ResultsIterator
    {
        try {
            return $this->getAllRowsWithTime(TimeString::now());
        } catch (InvalidTimeStringException $e) {
            throw new RuntimeException('Unexpected error getting rows',  $e->getCode(), $e);
        }
    }

    public function getDataRowsValidAtTime(array $data, string $time): array
    {
        return array_values(array_filter($data, fn(array $row): bool => $row[$this->validFromColumn] <= $time && $row[$this->validUntilColumn] > $time));
    }

    function findRows(array $rowToMatch, int $maxResults = 0): ResultsIterator
    {
        try {
            return $this->findRowsWithTime($rowToMatch, $maxResults, TimeString::now());
        } catch (Exception\InvalidTimeStringException $e) {
            throw new RuntimeException("Unexpected exception: " . $e->getMessage(),  $e->getCode(), $e);
        }
    }

    public function search(array $searchSpecArray, int $searchType = self::SEARCH_AND, int $maxResults = 0): ResultsIterator
    {
        try {
            return $this->searchWithTime($searchSpecArray, $searchType, TimeString::now(), $maxResults);
        } catch (Exception\InvalidTimeStringException $e) {
            throw new RuntimeException("Unexpected exception: " . $e->getMessage(),  $e->getCode(), $e);
        }
    }

    public function updateRow(array $theRow): void
    {
        try {
            $this->updateRowWithTime($theRow, TimeString::now());
        } catch (InvalidRowUpdateTime|Exception\InvalidTimeStringException $e) {
            throw new RuntimeException('Unexpected error updating row',  $e->getCode(), $e);
        } catch (RowDoesNotExist $e) {
            throw new InvalidRowForUpdate($e->getMessage(), $e->getCode(), $e);
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
        $validRows = $this->getDataRowsValidAtTime($this->theData, TimeString::now());
        foreach ($validRows as $row) {
            if (array_key_exists($key, $row) && $row[$key] === $value) {
                return $row[$this->idColumnName];
            }
        }

        return self::NULL_ROW_ID;
    }

    public function getMaxValueInColumn(string $columnName): int
    {
        $values = array_column(
            $this->getDataRowsValidAtTime($this->theData, TimeString::now()),
            $columnName
        );
        return $values === [] ? 0 : max($values);
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
            if ($row[$this->validFromColumn] <= $timeString && $row[$this->validUntilColumn] > $timeString) {
                $ids[] = $row[$this->idColumnName];
            }
        }
        $ids = array_values(array_unique($ids, SORT_NUMERIC));
        sort($ids, SORT_NUMERIC);
        return new ArrayIterator($ids);
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

    public function getValidFromColumnName(): string
    {
        return $this->validFromColumn;
    }

    public function getValidUntilColumnName(): string
    {
        return $this->validUntilColumn;
    }

    public function setValidFromColumnName(string $validFromColumnName): void
    {
        $this->validateTimeColumnName($validFromColumnName);
        $this->validFromColumn = $validFromColumnName;
    }

    public function setValidUntilColumnName(string $validUntilColumnName): void
    {
        $this->validateTimeColumnName($validUntilColumnName);
        $this->validUntilColumn = $validUntilColumnName;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateTimeColumnName(string $columnName): void
    {
        if ($columnName === '' || trim($columnName) !== $columnName || preg_match('/\s/', $columnName) === 1) {
            throw new InvalidArgumentException('Time column names must be non-empty and contain no whitespace');
        }
    }

    private function setErrorMessage(string $message): void
    {
        $this->errorMessage = $message;
    }

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

    public function getAllRowsWithTime(string $timeString): ResultsIterator
    {
        $timeString = $this->getValidTimeString($timeString, 'getAllRowsWithTime');
        return new ArrayResultsIterator($this->sanitizedRowSet($this->getDataRowsValidAtTime($this->theData, $timeString)));
    }

    public function getConsistencyIssues(array|null $idsToCheck): array
    {
        if ($idsToCheck === null) {
            // check everything!
            $idsToCheck = array_values(array_unique(
                array_column($this->theData, $this->idColumnName),
                SORT_NUMERIC
            ));
            sort($idsToCheck, SORT_NUMERIC);
        }
        $issues = [];
        foreach($idsToCheck as $id) {
            $rowHistory = $this->getRowHistory($id);
            $issues = [...$issues, ...UnitemporalConsistencyChecker::getConsistencyIssues($id, $rowHistory, $this->validFromColumn, $this->validUntilColumn)];
        }
        return $issues;
    }
}