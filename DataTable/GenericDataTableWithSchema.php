<?php

namespace ThomasInstitut\DataTable;

use Iterator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\Exception\InvalidRow;
use ThomasInstitut\DataTable\Exception\InvalidRowForUpdate;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\IdGenerator\IdGenerator;
use ThomasInstitut\DataTable\ResultsIterator\ResultsIterator;
use ThomasInstitut\DataTable\ResultsIterator\TranslatedResultsIterator;
use ThomasInstitut\DataTable\Schema\ColumnDefArray;
use ThomasInstitut\DataTable\Schema\ColumnDefArrayValidator;
use ThomasInstitut\DataTable\Schema\ColumnDefinition;
use ThomasInstitut\DataTable\Schema\GenericRowTranslator;
use ThomasInstitut\DataTable\Schema\NoOpRowValueTranslator;
use ThomasInstitut\DataTable\Schema\RowValueTranslator;
use ThomasInstitut\DataTable\Schema\SearchSpecTranslator;
use Traversable;

class GenericDataTableWithSchema implements DataTableWithSchema
{
    private string $idKey;
    private string $idDbColumn;

    private readonly GenericRowTranslator $rowTranslator;

    protected LoggerInterface $logger;

    /**
     * @param DataTable $dataTable
     * @param array<ColumnDefinition $columnDefinitions
     * @param RowValueTranslator $rowValueTranslator
     * @throws InvalidColumnDefinitionsArray
     */
    public function __construct(private readonly DataTable $dataTable,
                                private readonly array $columnDefinitions,
                                private readonly RowValueTranslator $rowValueTranslator = new NoOpRowValueTranslator()
    )
    {
        $errors = ColumnDefArrayValidator::validate($this->columnDefinitions);
        if (count($errors) > 0) {
            throw new InvalidColumnDefinitionsArray('Invalid column definitions: ' . implode(', ', $errors));
        }
        $this->idKey = ColumnDefArray::getIdKey($this->columnDefinitions);
        $this->idDbColumn = ColumnDefArray::getIdDbColumn($this->columnDefinitions);
        $this->dataTable->setIdColumnName($this->idDbColumn);
        $this->rowTranslator = new GenericRowTranslator($this->rowValueTranslator, $this->columnDefinitions);
        $this->logger = new NullLogger();
        $this->dataTable->setLogger($this->logger);
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return $this->getAllRows();
    }

    /**
     * @inheritDoc
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->rowExists(intval($offset));
    }

    /**
     * @inheritDoc
     */
    public function offsetGet(mixed $offset): ?array
    {
        return $this->getRow(intval($offset));
    }

    /**
     * @inheritDoc
     * @throws RowAlreadyExists
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->createRow($value);
            return;
        }
        $id = intval($offset);
        $value[$this->idKey] = $id;
        if ($this->rowExists($id)) {
            try {
                $this->updateRow($value);
            } catch (InvalidRowForUpdate $e) {
                // this should never happen?
                throw new RuntimeException('Invalid row for update', 0, $e);
            }
        } else {
            try {
                $this->createRow($value);
            } catch (RowAlreadyExists) { // @codeCoverageIgnore
                // this should never happen
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->deleteRow(intval($offset));
    }

    /**
     * @inheritDoc
     */
    public function setIdGenerator(IdGenerator $ig): void
    {
        $this->dataTable->setIdGenerator($ig);
    }

    /**
     * @inheritDoc
     */
    public function rowExists(int $rowId): bool
    {
        return $this->dataTable->rowExists($rowId);
    }

    /**
     * @inheritDoc
     */
    public function createRow(array $theRow): int
    {
        return $this->dataTable->createRow($this->rowTranslator->inputRowToDb($theRow));
    }

    /**
     * @inheritDoc
     */
    public function getRow(int $rowId): ?array
    {
        $rowFromDb = $this->dataTable->getRow($rowId);
        if ($rowFromDb === null) {
            return null;
        }
        return $this->rowTranslator->dbRowToOutputRow($rowFromDb);
    }

    /**
     * @inheritDoc
     */
    public function getAllRows(): ResultsIterator
    {
        return new TranslatedResultsIterator($this->dataTable->getAllRows(), $this->rowTranslator);
    }

    /**
     * @inheritDoc
     */
    public function deleteRow(int $rowId): int
    {
        return $this->dataTable->deleteRow($rowId);
    }

    /**
     * @inheritDoc
     */
    function findRows(array $rowToMatch, int $maxResults = 0): ResultsIterator
    {
        return $this->dataTable->findRows($this->rowTranslator->inputRowToDb($rowToMatch), $maxResults);
    }

    /**
     * @inheritDoc
     */
    public function search(array $searchSpecArray, SearchType $searchType = SearchType::And, int $maxResults = 0): ResultsIterator
    {
        $dtSearchType = SearchSpecTranslator::searchTypeToDataTableSearchType($searchType);
        return $this->dataTable->search(SearchSpecTranslator::toDataTableSearchSpecArray($searchSpecArray, $this->columnDefinitions, $this->rowTranslator), $dtSearchType, $maxResults);
    }

    /**
     * @inheritDoc
     */
    public function updateRow(array $theRow): void
    {
        try {
            $this->dataTable->updateRow($this->rowTranslator->inputRowToDb($theRow));
        } catch (InvalidRowForUpdate $e) {
            throw new InvalidRow($e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function supportsTransactions(): bool
    {
        return $this->dataTable->supportsTransactions();
    }

    /**
     * @inheritDoc
     */
    public function startTransaction(): bool
    {
        return $this->dataTable->startTransaction();
    }

    /**
     * @inheritDoc
     */
    public function commit(): bool
    {
        return $this->dataTable->commit();
    }

    /**
     * @inheritDoc
     */
    public function rollBack(): bool
    {
        return $this->dataTable->rollBack();
    }

    /**
     * @inheritDoc
     */
    public function isInTransaction(): bool
    {
        return $this->dataTable->isInTransaction();
    }

    /**
     * @inheritDoc
     */
    public function isUnderlyingDatabaseInTransaction(): bool
    {
        return $this->dataTable->isUnderlyingDatabaseInTransaction();
    }

    /**
     * @inheritDoc
     */
    public function getMaxValueInColumn(string $columnName): int
    {
        $colDef = $this->getDefForColumn($columnName);
        if ($colDef === null) {
            throw new InvalidArgumentException("Column $columnName not found");
        }

        return $this->dataTable->getMaxValueInColumn($colDef->dbColumn ?? $colDef->rowKey);
    }

    private function getDefForColumn(string $columnName): ColumnDefinition|null
    {
       for ($i = 0; $i < count($this->columnDefinitions); $i++) {
           if ($this->columnDefinitions[$i]->rowKey === $columnName) {
               return $this->columnDefinitions[$i];
           }
       }

       return null;
    }

    /**
     * @inheritDoc
     */
    public function getMaxId(): int
    {
        return $this->dataTable->getMaxId();
    }

    /**
     * @inheritDoc
     */
    public function getUniqueIds(): Iterator
    {
        return $this->dataTable->getUniqueIds();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->dataTable->getName();
    }

    /**
     * @inheritDoc
     */
    public function setLogger(LoggerInterface $logger): void
    {
       $this->logger = $logger;
       $this->dataTable->setLogger($logger);
    }
}