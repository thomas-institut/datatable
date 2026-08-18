<?php

namespace ThomasInstitut\DataTable;

use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\Exception\InvalidRow;
use ThomasInstitut\DataTable\Exception\InvalidRowForUpdate;
use ThomasInstitut\DataTable\ResultsIterator\ResultsIterator;
use ThomasInstitut\DataTable\ResultsIterator\TranslatedResultsIterator;
use ThomasInstitut\DataTable\Schema\ColumnDefArray;
use ThomasInstitut\DataTable\Schema\DataTableSchema;
use ThomasInstitut\DataTable\Schema\NoOpRowValueTranslator;
use ThomasInstitut\DataTable\Schema\RowValueTranslator;
use ThomasInstitut\DataTable\Schema\SearchSpecTranslator;

class GenericUnitemporalDataTableWithSchema extends GenericDataTableWithSchema implements UnitemporalDataTableWithSchema
{
    protected UnitemporalDataTable $unitemporalDataTable;

    public function __construct(UnitemporalDataTable $dataTable, DataTableSchema $dataTableSchema, RowValueTranslator $rowValueTranslator = new NoOpRowValueTranslator(), ?array $supportedSearchConditions = null, ?array $supportedDataTypes = null)
    {
        $errors = ColumnDefArray::validateUnitemporal($dataTableSchema->columnDefinitions);
        if (count($errors) > 0) {
            throw new InvalidColumnDefinitionsArray('Invalid column definitions: ' . implode(', ', $errors));
        }
        $supportedDataTypes ??= array_merge(DataTableWithSchema::MandatorySupportedDataTypes, UnitemporalDataTableWithSchema::AdditionalRequiredDataTypes);
        $supportedDataTypes = $this->getCompliantSupportedDataTypes($supportedDataTypes);
        parent::__construct($dataTable, $dataTableSchema, $rowValueTranslator, $supportedSearchConditions, $supportedDataTypes);
        $this->unitemporalDataTable = $dataTable;
    }

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
        return $this->unitemporalDataTable->createRowWithTime($theRow, $timeString);
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
        return $this->unitemporalDataTable->getRowWithTime($rowId, $timeString);
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