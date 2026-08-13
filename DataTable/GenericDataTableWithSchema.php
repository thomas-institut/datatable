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
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\ColumnDefArray;
use ThomasInstitut\DataTable\Schema\ColumnDefArrayValidator;
use ThomasInstitut\DataTable\Schema\ColumnDefinition;
use ThomasInstitut\DataTable\Schema\DataTableSchema;
use ThomasInstitut\DataTable\Schema\GenericRowTranslator;
use ThomasInstitut\DataTable\Schema\NoOpRowValueTranslator;
use ThomasInstitut\DataTable\Schema\RowValueTranslator;
use ThomasInstitut\DataTable\Schema\SearchSpecTranslator;
use ThomasInstitut\DataTable\Schema\SupportedSearchCondition;
use Traversable;

class GenericDataTableWithSchema implements DataTableWithSchema
{
    private string $idKey;
    private string $idDbColumn;

    /** @var array<SupportedSearchCondition> */
    private readonly array $supportedSearchConditions;

    /** @var array<ColumnDataType> */
    private readonly array $supportedDataTypes;

    private readonly array $columnDefinitions;

    private readonly GenericRowTranslator $rowTranslator;

    protected LoggerInterface $logger;

    /**
     * @param DataTable $dataTable
     * @param DataTableSchema $dataTableSchema
     * @param RowValueTranslator $rowValueTranslator
     * @param array<SupportedSearchCondition>|null $supportedSearchConditions
     * @param array<ColumnDataType>|null $supportedDataTypes
     * @throws InvalidColumnDefinitionsArray
     */
    public function __construct(private readonly DataTable          $dataTable,
                                DataTableSchema $dataTableSchema,
                                private readonly RowValueTranslator $rowValueTranslator = new NoOpRowValueTranslator(),
                                ?array $supportedSearchConditions = null,
                                ?array $supportedDataTypes = null
    )
    {
        $this->columnDefinitions = $dataTableSchema->columnDefinitions;
        $this->supportedDataTypes = $this->getCompliantSupportedDataTypes($supportedDataTypes ?? DataTableWithSchema::MandatorySupportedDataTypes);
        $errors = ColumnDefArrayValidator::validate($this->columnDefinitions, $this->supportedDataTypes);
        if (count($errors) > 0) {
            throw new InvalidColumnDefinitionsArray('Invalid column definitions: ' . implode(', ', $errors));
        }
        $this->idKey = ColumnDefArray::getIdKey($this->columnDefinitions);
        $this->idDbColumn = ColumnDefArray::getIdDbColumn($this->columnDefinitions);
        $this->dataTable->setIdColumnName($this->idDbColumn);
        $this->rowTranslator = new GenericRowTranslator($this->rowValueTranslator, $this->columnDefinitions);
        $this->logger = new NullLogger();
        $this->dataTable->setLogger($this->logger);
        $this->supportedSearchConditions = $supportedSearchConditions ?? SupportedSearchCondition::reasonableDefaults();
    }

    /**
     * Returns an array of supported data types that includes all the mandatory data types.
     *
     * @param array<ColumnDataType> $supportedDataTypes
     * @return array<ColumnDataType>
     */
    private function getCompliantSupportedDataTypes(array $supportedDataTypes) : array
    {
        foreach(DataTableWithSchema::MandatorySupportedDataTypes as $dataType) {
            if (!in_array($dataType, $supportedDataTypes)) {
                $supportedDataTypes[] = $dataType;
            }
        }
        return $supportedDataTypes;
    }

    public function getSupportedDataTypes() : array
    {
        return $this->supportedDataTypes;
    }

    public function getInnerDataTable() : DataTable
    {
        return $this->dataTable;
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
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
     * @throws InvalidRow
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
            $this->updateRow($value);
        } else {
            try {
                $this->createRow($value);
            } catch (RowAlreadyExists) { // @codeCoverageIgnore
                // this will never happen unless the underlying database is corrupted
                throw new RuntimeException('Unexpected "Row already exists" exception'); // @codeCoverageIgnore
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
     * @codeCoverageIgnore
     */
    public function setIdGenerator(IdGenerator $ig): void
    {
        $this->dataTable->setIdGenerator($ig);
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
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
        return new TranslatedResultsIterator(
            $this->dataTable->findRows($this->rowTranslator->inputRowToDb($rowToMatch, false), $maxResults),
            $this->rowTranslator,
        );
    }

    /**
     * @inheritDoc
     */
    public function search(array $searchSpecArray, SearchType $searchType = SearchType::And, int $maxResults = 0): ResultsIterator
    {
        $dtSearchType = SearchSpecTranslator::searchTypeToDataTableSearchType($searchType);
        $dtSearchSpecArray = SearchSpecTranslator::toDataTableSearchSpecArray($searchSpecArray, $this->columnDefinitions, $this->rowTranslator, $this->getSupportedSearchConditions());
        return new TranslatedResultsIterator(
            $this->dataTable->search($dtSearchSpecArray, $dtSearchType, $maxResults),
            $this->rowTranslator
        );
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
     * @codeCoverageIgnore
     */
    public function supportsTransactions(): bool
    {
        return $this->dataTable->supportsTransactions();
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function startTransaction(): bool
    {
        return $this->dataTable->startTransaction();
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function commit(): bool
    {
        return $this->dataTable->commit();
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function rollBack(): bool
    {
        return $this->dataTable->rollBack();
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function isInTransaction(): bool
    {
        return $this->dataTable->isInTransaction();
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
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

        $numericTypes = [ColumnDataType::Integer, ColumnDataType::Id];

        if (!in_array($colDef->type, $numericTypes)) {
            throw new InvalidArgumentException("Column $columnName is not numeric");
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
     * @codeCoverageIgnore
     */
    public function getMaxId(): int
    {
        return $this->dataTable->getMaxId();
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function getUniqueIds(): Iterator
    {
        return $this->dataTable->getUniqueIds();
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return $this->dataTable->getName();
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        $this->dataTable->setLogger($logger);
    }


    /**
     * @inheritDoc
     */
    public function getSupportedSearchConditions(): array
    {
        return $this->supportedSearchConditions;

    }
}