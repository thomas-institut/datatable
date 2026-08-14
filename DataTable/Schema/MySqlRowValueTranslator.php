<?php

namespace ThomasInstitut\DataTable\Schema;

class MySqlRowValueTranslator implements RowValueTranslator
{

    /**
     * @inheritDoc
     */
    public function rowValueToDbValue(mixed $value, ColumnDataType $type): mixed
    {
        return match($type) {
            ColumnDataType::Serializable => serialize($value),
            ColumnDataType::Boolean => $value ? 1 : 0,
            ColumnDataType::Text,
            ColumnDataType::VarChar,
            ColumnDataType::Integer,
            ColumnDataType::Id,
            ColumnDataType::TimeString => $value,
        };
    }

    /**
     * @inheritDoc
     */
    public function dbValueToRowValue(mixed $value, ColumnDataType $type): mixed
    {
        return match($type) {
            ColumnDataType::Serializable => unserialize($value),
            ColumnDataType::Boolean => $value === 1,
            ColumnDataType::Integer,
            ColumnDataType::Id => intval($value),
            ColumnDataType::Text,
            ColumnDataType::VarChar,
            ColumnDataType::TimeString => $value,
        };
    }
}