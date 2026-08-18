<?php

namespace ThomasInstitut\DataTable\Schema;

class ColumnDefArray
{
    /**
     * Returns the row key of the id column or null if not found.
     *
     * @param array<int, ColumnDefinition> $columnDefinitions
     */
    public static function getIdKey(array $columnDefinitions): string|null
    {

        $defs = self::getColumnDefsForType($columnDefinitions, ColumnDataType::Id);
        if (count($defs) > 0) {
            return $defs[0]->rowKey;
        }
        return null;
    }

    /**
     * Returns the column definition for the given type or an empty array if not found.
     * @param ColumnDefinition[] $columDefinitions
     * @return ColumnDefinition[]
     */
    public static function getColumnDefsForType(array $columDefinitions, ColumnDataType $type): array
    {
        $defs = [];
        foreach ($columDefinitions as $columnDefinition) {
            if ($columnDefinition->type === $type) {
                $defs[] = $columnDefinition;
            }
        }
        return $defs;
    }

    /**
     * @param array<int, ColumnDefinition> $columnDefinitions
     */
    public static function getIdDbColumn(array $columnDefinitions): string|null
    {
        $defs = self::getColumnDefsForType($columnDefinitions, ColumnDataType::Id);
        if (count($defs) > 0) {
            return $defs[0]->dbColumn ?? $defs[0]->rowKey;
        }
        return null;
    }

    public static function getColumnDef(array $columnDefinitions, string $rowKey): ColumnDefinition|null
    {
        $counter = count($columnDefinitions);
        for ($i = 0; $i < $counter; $i++) {
            if ($columnDefinitions[$i]->rowKey === $rowKey) {
                return $columnDefinitions[$i];
            }
        }
        return null;
    }

    /**
     * Returns the column definitions indexed by their db column name.
     *
     * @param array<ColumnDefinition> $columnDefinitions
     * @return array<string, ColumnDefinition>
     */
    public static function getDefsByDbKey(array $columnDefinitions): array
    {
        $defsByDbKey = [];
        foreach ($columnDefinitions as $columnDef) {
            if ($columnDef->dbColumn !== null) {
                $defsByDbKey[$columnDef->dbColumn] = $columnDef;
            } else {
                $defsByDbKey[$columnDef->rowKey] = $columnDef;
            }
        }
        return $defsByDbKey;
    }

    /**
     * Returns the column definitions indexed by their row key.
     *
     * @param array<ColumnDefinition> $columnDefinitions
     * @return array<string, ColumnDefinition>
     */
    public static function getDefsByRowKey(array $columnDefinitions): array
    {
        $defsByRowKey = [];
        foreach ($columnDefinitions as $columnDef) {
            $defsByRowKey[$columnDef->rowKey] = $columnDef;
        }
        return $defsByRowKey;
    }

    public static function validateUnitemporal(array $columnDefArray): array
    {
        $errors = [];

        foreach ([ColumnDataType::ValidFrom, ColumnDataType::ValidUntil] as $type) {
            $defs = self::getColumnDefsForType($columnDefArray, $type);
            if (count($defs) === 0) {
                $errors[] = "Unitemporal data table must have at least one column of type '$type->value'.";
                continue;
            }

            if (count($defs) > 1) {
                $errors[] = "Unitemporal data table must have at most one column of type '$type->value'.";
            }
        }

        return $errors;
    }

    /**
     * Checks the column definition array for validity.
     * Returns an array of errors if the column definition array is invalid.
     *
     * @param ColumnDefinition[] $columnDefArray
     * @param ColumnDataType[] $supportedDataTypes
     * @return string[]
     */
    public static function validate(array $columnDefArray, array $supportedDataTypes): array
    {
        $errors = [];
        $rowKeys = [];
        $dbColumns = [];
        $idKey = null;

        foreach ($columnDefArray as $index => $columnDef) {
            if (!is_int($index)) {
                $errors[] = "Column definition array key must be an integer, but found '$index' of type " . gettype($index);
                continue;
            }
            if (!$columnDef instanceof ColumnDefinition) {
                $errors[] = "Element at index $index is not a ColumnDef object.";
                continue;
            }

            if (!in_array($columnDef->type, $supportedDataTypes)) {
                $errors[] = "Column at index $index has unsupported data type: '{$columnDef->type->value}'";
            }

            if ($columnDef->type === ColumnDataType::Id) {
                $idKey = $columnDef->rowKey;
            }

            if (!self::isValidKey($columnDef->rowKey)) {
                $errors[] = "Column at index $index has invalid rowKey: '$columnDef->rowKey'";
            }

            // if $dbColumn is not null, it must be trimmed string, not empty and a single word
            if ($columnDef->dbColumn !== null && !self::isValidKey($columnDef->dbColumn)) {
                $errors[] = "Column at index $index has invalid dbColumn: '$columnDef->dbColumn'";
            }

            // some types must be marked as required
            if (in_array($columnDef->type, ColumnDataType::NoDefaultTypes) && $columnDef->required === false) {
                $errors[] = "Column at index $index must have required = true since it is of type {$columnDef->type->value}.";
            }

            // $rowKey and $dbColumn must be unique in the array.
            $effectiveDbColumn = $columnDef->dbColumn ?? $columnDef->rowKey;

            if (in_array($columnDef->rowKey, $rowKeys)) {
                $errors[] = "Duplicate rowKey: '$columnDef->rowKey'.";
            }
            $rowKeys[] = $columnDef->rowKey;

            if ($effectiveDbColumn !== '' && in_array($effectiveDbColumn, $dbColumns)) {
                $errors[] = "Duplicate database column name: '$effectiveDbColumn'.";
            }
            $dbColumns[] = $effectiveDbColumn;

            // check if type is Varchar: $typeLength must be > 0
            if ($columnDef->type === ColumnDataType::VarChar && $columnDef->typeLength <= 0) {
                $errors[] = "Column '$columnDef->rowKey' is Varchar but has invalid typeLength: $columnDef->typeLength.";
            }
        }

        if ($idKey === null) {
            $errors[] = "No id column found in column definitions";
        }

        return $errors;
    }

    private static function isValidKey(string $key): bool
    {
        return $key !== '' && trim($key) === $key && !str_contains($key, ' ');
    }

}