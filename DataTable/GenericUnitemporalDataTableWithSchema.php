<?php

namespace ThomasInstitut\DataTable;

use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\Exception\InvalidRow;
use ThomasInstitut\DataTable\Exception\InvalidRowForUpdate;
use ThomasInstitut\DataTable\ResultsIterator\ResultsIterator;
use ThomasInstitut\DataTable\ResultsIterator\TranslatedResultsIterator;
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\ColumnDefArray;
use ThomasInstitut\DataTable\Schema\DataTableSchema;
use ThomasInstitut\DataTable\Schema\NoOpRowValueTranslator;
use ThomasInstitut\DataTable\Schema\RowValueTranslator;
use ThomasInstitut\DataTable\Schema\SearchSpecTranslator;

class GenericUnitemporalDataTableWithSchema extends GenericDataTableWithSchema implements UnitemporalDataTableWithSchema
{
    protected UnitemporalDataTable $unitemporalDataTable;

    public function __construct(UnitemporalDataTable $unitemporalDataTable,
                                DataTableSchema      $dataTableSchema,
                                RowValueTranslator   $rowValueTranslator = new NoOpRowValueTranslator(),
                                ?array               $supportedSearchConditions = null,
                                ?array               $supportedDataTypes = null)
    {

        $validFromDefs = ColumnDefArray::getColumnDefsForType($dataTableSchema->columnDefinitions, ColumnDataType::ValidFrom);
        if (empty($validFromDefs)) {
             throw new InvalidColumnDefinitionsArray('Missing valid_from column');
        }
        if (count($validFromDefs) > 1) {
             throw new InvalidColumnDefinitionsArray('Multiple valid_from columns');
        }
        $validFromDbColName = $validFromDefs[0]->dbColumn ?? $validFromDefs[0]->rowKey;


        $validUntilDefs = ColumnDefArray::getColumnDefsForType($dataTableSchema->columnDefinitions, ColumnDataType::ValidUntil);
        if (empty($validUntilDefs)) {
            throw new InvalidColumnDefinitionsArray('Missing valid_until column');
        }
        if (count($validUntilDefs) > 1) {
            throw new InvalidColumnDefinitionsArray('Multiple valid_until columns');
        }
        $validUntilDbColName = $validUntilDefs[0]->dbColumn ?? $validUntilDefs[0]->rowKey;

        try {
            $unitemporalDataTable->setValidFromColumnName($validFromDbColName);
            $unitemporalDataTable->setValidUntilColumnName($validUntilDbColName);
        } catch (Exception\InvalidArgumentException) {
            throw new InvalidColumnDefinitionsArray('Invalid valid_from or valid_until column name');
        }

        $supportedDataTypes ??= array_merge(DataTableWithSchema::MandatorySupportedDataTypes, UnitemporalDataTableWithSchema::AdditionalRequiredDataTypes);
        $supportedDataTypes = $this->getCompliantSupportedDataTypes($supportedDataTypes);
        parent::__construct($unitemporalDataTable, $dataTableSchema, $rowValueTranslator, $supportedSearchConditions, $supportedDataTypes);
        $this->unitemporalDataTable = $unitemporalDataTable;
    }

    /**
     * @param array<ColumnDataType> $supportedDataTypes
     * @return array<int, ColumnDataType>
     */
    private function getCompliantSupportedDataTypes(array $supportedDataTypes): array
    {
        foreach (UnitemporalDataTableWithSchema::AdditionalRequiredDataTypes as $dataType) {
            if (!in_array($dataType, $supportedDataTypes)) {
                $supportedDataTypes[] = $dataType;
            }
        }
        return $supportedDataTypes;
    }

    /**
     * @inheritDoc
     */
    public function createRowWithTime(array $theRow, string $timeString): int
    {
        return $this->unitemporalDataTable->createRowWithTime($this->rowTranslator->inputRowToDb($theRow), $timeString);
    }

    /**
     * @inheritDoc
     */
    public function rowExistsWithTime(int $rowId, string $timeString): bool
    {
        return $this->unitemporalDataTable->rowExistsWithTime($rowId, $timeString);
    }

    /**
     * @inheritDoc
     */
    public function getRowWithTime(int $rowId, string $timeString): ?array
    {
        $dbRow = $this->unitemporalDataTable->getRowWithTime($rowId, $timeString);
        if ($dbRow === null) {
            return null;
        }
        return $this->rowTranslator->dbRowToOutputRow($dbRow);
    }

    /**
     * @inheritDoc
     */
    public function findRowsWithTime(array $rowToMatch, int $maxResults, string $timeString): ResultsIterator
    {
        return new TranslatedResultsIterator(
            $this->unitemporalDataTable->findRowsWithTime($this->rowTranslator->inputRowToDb($rowToMatch, false), $maxResults, $timeString),
            $this->rowTranslator,
        );
    }

    /**
     * @inheritDoc
     */
    public function searchWithTime(array $searchSpecArray, SearchType $searchType, string $timeString, int $maxResults = 0): ResultsIterator
    {
        $dtSearchType = SearchSpecTranslator::searchTypeToDataTableSearchType($searchType);
        $dtSearchSpecArray = SearchSpecTranslator::toDataTableSearchSpecArray($searchSpecArray, $this->columnDefinitions, $this->rowTranslator, $this->getSupportedSearchConditions());
        return new TranslatedResultsIterator(
            $this->unitemporalDataTable->searchWithTime($dtSearchSpecArray, $dtSearchType, $timeString, $maxResults),
            $this->rowTranslator
        );
    }

    /**
     * @inheritDoc
     */
    public function updateRowWithTime(array $theRow, string $timeString): void
    {
        try {
            $this->unitemporalDataTable->updateRowWithTime($this->rowTranslator->inputRowToDb($theRow), $timeString);
        } catch (InvalidRowForUpdate $e) {
            throw new InvalidRow($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteRowWithTime(int $rowId, string $timeString): int
    {
        return $this->unitemporalDataTable->deleteRowWithTime($rowId, $timeString);
    }

    /**
     * @inheritDoc
     */
    public function getRowHistory(int $rowId): array
    {
        return $this->unitemporalDataTable->getRowHistory($rowId);
    }

}