<?php

namespace ThomasInstitut\DataTable\Schema;

use ThomasInstitut\DataTable\DataTable;
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\Exception\InvalidRow;
use ThomasInstitut\DataTable\Exception\InvalidSearchSpec;
use ThomasInstitut\DataTable\SearchCondition;
use ThomasInstitut\DataTable\SearchSpec;
use ThomasInstitut\DataTable\SearchType;

class SearchSpecTranslator
{
    /**
     * @param array<int, ColumnDefinition> $columnDefs
     * @param array<SupportedSearchCondition> $supportedSearchConditions
     * @return array<string, int|string>
     * @throws InvalidSearchSpec
     * @throws InvalidColumnDefinitionsArray
     */
    public static function toDataTableSearchSpec(SearchSpec $searchSpec, array $columnDefs, RowTranslator $rowTranslator, array $supportedSearchConditions): array
    {
        $columnDef = ColumnDefArray::getColumnDef($columnDefs, $searchSpec->column);
        if (!$columnDef instanceof ColumnDefinition) {
            throw new InvalidSearchSpec("Column '$searchSpec->column' does not exist.");
        }
        try {
            if (!ColumnDefinition::valueIsValidForColumn($searchSpec->value, $columnDef)) {
                throw new InvalidSearchSpec("Value '$searchSpec->value' is not valid for column '$searchSpec->column'.");
            }
        } catch (InvalidArgumentException $e) {
            // this means a bad columnDef
            throw new InvalidColumnDefinitionsArray("Unexpected error validating column '$searchSpec->column': " . $e->getMessage(), $e->getCode(), $e);
        }

        $supportedSearchCondition = array_values(array_filter($supportedSearchConditions, fn(SupportedSearchCondition $supportedSearchCondition): bool => $supportedSearchCondition->type === $columnDef->type));
        if (count($supportedSearchCondition) === 0) {
            throw new InvalidSearchSpec("Search condition '{$searchSpec->condition->value}' is not supported for column '$searchSpec->column' of type '{$columnDef->type->value}'.");
        }

        $validConditions = $supportedSearchCondition[0]->conditions;
        if (!in_array($searchSpec->condition, $validConditions)) {
            throw new InvalidSearchSpec("Search condition '{$searchSpec->condition->value}' is not supported for column '$searchSpec->column' of type '{$columnDef->type->value}'.");
        }

        $rowToTranslate = [$searchSpec->column => $searchSpec->value];
        try {
            $translatedRow = $rowTranslator->inputRowToDb($rowToTranslate, false);
        } catch (InvalidRow) {
            throw new InvalidSearchSpec("Invalid value '$searchSpec->value' for column '$searchSpec->column' of type '{$columnDef->type->value}'.");
        }
        $translatedColumnName = array_keys($translatedRow)[0];
        $translatedValue = $translatedRow[$translatedColumnName];

        return [
            'column' => $translatedColumnName,
            'condition' => self::searchConditionToDataTableCondition($searchSpec->condition),
            'value' => $translatedValue,
        ];
    }

    /**
     * @param array<SupportedSearchCondition> $supportedSearchConditions
     * @param array<int, SearchSpec> $searchSpecArray
     * @param array<int, ColumnDefinition> $columnDefs
     * @return array<array<string, int|string>>
     * @throws InvalidSearchSpec|InvalidColumnDefinitionsArray
     */
    public static function toDataTableSearchSpecArray(array $searchSpecArray, array $columnDefs, RowTranslator $rowTranslator, array $supportedSearchConditions): array {
        return array_map(fn(SearchSpec $searchSpec): array => self::toDataTableSearchSpec($searchSpec, $columnDefs, $rowTranslator, $supportedSearchConditions), $searchSpecArray);
    }

    public static function searchTypeToDataTableSearchType(SearchType $searchType): int {
        return match ($searchType) {
            SearchType::And => DataTable::SEARCH_AND,
            SearchType::Or => DataTable::SEARCH_OR,
        };
    }

    public static function searchConditionToDataTableCondition(SearchCondition $searchCondition): int {
        return match($searchCondition) {
            SearchCondition::Equals => DataTable::COND_EQUAL_TO,
            SearchCondition::NotEquals => DataTable::COND_NOT_EQUAL_TO,
            SearchCondition::LessThan => DataTable::COND_LESS_THAN,
            SearchCondition::LessThanOrEquals => DataTable::COND_LESS_OR_EQUAL_TO,
            SearchCondition::GreaterThan => DataTable::COND_GREATER_THAN,
            SearchCondition::GreaterThanOrEquals => DataTable::COND_GREATER_OR_EQUAL_TO,
        };
    }
}