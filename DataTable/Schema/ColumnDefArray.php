<?php

namespace ThomasInstitut\DataTable\Schema;

class ColumnDefArray
{
    /**
     * Returns the row key of the id column or null if not found.
     *
     * @param array<int, ColumnDefinition> $columnDefinitions
     */
    public static function getIdKey(array $columnDefinitions): string| null {
        for ($i = 0; $i < count($columnDefinitions); $i++) {
             if ($columnDefinitions[$i]->type === ColumnDataType::Id) {
                return $columnDefinitions[$i]->rowKey;
            }
        }
        return null;
    }

    /**
     * @param array<int, ColumnDefinition> $columnDefinitions
     * @return string|null
     */
    public static function getIdDbColumn(array $columnDefinitions): string| null {
        for ($i = 0; $i < count($columnDefinitions); $i++) {
            if ($columnDefinitions[$i]->type === ColumnDataType::Id) {
                return $columnDefinitions[$i]->dbColumn ?? $columnDefinitions[$i]->rowKey;
            }
        }
        return null;
    }

    public static function getColumnDef(array $columnDefinitions, string $rowKey): ColumnDefinition | null {
        for ($i = 0; $i < count($columnDefinitions); $i++) {
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
    public static function getDefsByDbKey(array $columnDefinitions): array {
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
    public static function getDefsByRowKey(array $columnDefinitions): array {
        $defsByRowKey = [];
        foreach ($columnDefinitions as $columnDef) {
            $defsByRowKey[$columnDef->rowKey] = $columnDef;
        }
        return $defsByRowKey;
    }

}