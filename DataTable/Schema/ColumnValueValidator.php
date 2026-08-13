<?php

namespace ThomasInstitut\DataTable\Schema;

class ColumnValueValidator
{

    /**
     * @param mixed $value
     * @param ColumnDefinition $columnDefinition
     * @return bool
     */
    public static function validate(mixed $value, ColumnDefinition $columnDefinition): bool {
        if ($value === null) {
            if ($columnDefinition->type === ColumnDataType::Id) {
                return false;
            }
            return $columnDefinition->nullable;
        }

        return match ($columnDefinition->type) {
            ColumnDataType::Serializable => true,
            ColumnDataType::VarChar => is_string($value)
                && strlen($value) <= $columnDefinition->typeLength,
            ColumnDataType::Text => is_string($value),
            ColumnDataType::Id, ColumnDataType::Integer => is_int($value),
            ColumnDataType::Boolean => is_bool($value),
        };
    }
}