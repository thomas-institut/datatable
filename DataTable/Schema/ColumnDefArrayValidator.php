<?php

namespace ThomasInstitut\DataTable\Schema;

class ColumnDefArrayValidator
{

    /**
     * Checks the column definition array for validity.
     * Returns an array of errors if the column definition array is invalid.
     *
     * @param array<string,ColumnDefinition> $columnDefArray
     * @return string[]
     */
    public static function validate(array $columnDefArray): array
    {
        $errors = [];
        $rowKeys = [];
        $dbColumns = [];
        $idKey = null;

        foreach ($columnDefArray as $key => $columnDef) {
            if (!$columnDef instanceof ColumnDefinition) {
                $errors[] = "Element at key $key is not a ColumnDef object.";
                continue;
            }

            if ($columnDef->type === ColumnDataType::Id) {
                $idKey = $key;
            }

            if (!self::isValidKey($columnDef->rowKey)) {
                $errors[] = "Column at index $key has invalid rowKey: '$columnDef->rowKey'";
            }

            // if $dbColumn is not null, it must be trimmed string, not empty and a single word
            if ($columnDef->dbColumn !== null && !self::isValidKey($columnDef->dbColumn)) {
                $errors[] = "Column at index $key has invalid dbColumn: '$columnDef->dbColumn'";
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