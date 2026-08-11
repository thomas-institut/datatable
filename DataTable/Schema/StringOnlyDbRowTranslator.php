<?php

namespace ThomasInstitut\DataTable\Schema;

class StringOnlyDbRowTranslator implements RowValueTranslator {


    public function rowValueToDbValue(mixed $value, ColumnDataType $type): mixed
    {
        return match ($type) {
            ColumnDataType::Any => serialize($value),
            ColumnDataType::Integer => (string) $value,
            ColumnDataType::Boolean => $value ? '1' : '0',
            default => $value,
        };
    }

    public function dbValueToRowValue(mixed $value, ColumnDataType $type): mixed
    {
        return match ($type) {
            ColumnDataType::Any => unserialize($value),
            ColumnDataType::Integer => intval($value),
            ColumnDataType::Boolean => $value === '1',
            default => $value,
        };
    }
}