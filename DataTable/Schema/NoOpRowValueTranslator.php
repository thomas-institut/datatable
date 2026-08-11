<?php

namespace ThomasInstitut\DataTable\Schema;

class  NoOpRowValueTranslator implements RowValueTranslator
{

    public function rowValueToDbValue(mixed $value, ColumnDataType $type): mixed
    {
        return $value;
    }

    public function dbValueToRowValue(mixed $value, ColumnDataType $type): mixed
    {
        return $value;
    }
}