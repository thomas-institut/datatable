<?php

namespace ThomasInstitut\DataTable\Schema;

readonly class StringValuesDbRowValueTranslator implements RowValueTranslator {

    public function __construct(private string|null $nullValue = null)
    {
    }


    public function rowValueToDbValue(mixed $value, ColumnDataType $type): mixed
    {
        if ($value === null) {
            return $this->nullValue;
        }
        return match ($type) {
            ColumnDataType::Any => serialize($value),
            ColumnDataType::Integer, ColumnDataType::Id => (string) $value,
            ColumnDataType::Boolean => $value ? '1' : '0',
            ColumnDataType::VarChar, ColumnDataType::Text => $value,
        };
    }

    public function dbValueToRowValue(mixed $value, ColumnDataType $type): mixed
    {
        if ($value === null) {
            return $this->nullValue;
        }
        return match ($type) {
            ColumnDataType::Any => unserialize($value),
            ColumnDataType::Integer, ColumnDataType::Id => intval($value),
            ColumnDataType::Boolean => $value === '1',
            ColumnDataType::VarChar, ColumnDataType::Text => $value,
        };
    }
}