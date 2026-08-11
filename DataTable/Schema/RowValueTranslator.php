<?php

namespace ThomasInstitut\DataTable\Schema;

interface RowValueTranslator
{

    /**
     * Translates a value from the row format to the database format.
     *
     * @param mixed $value
     * @param ColumnDataType $type
     * @return mixed
     */
    public function rowValueToDbValue(mixed $value, ColumnDataType $type): mixed;

    /**
     * Translates a value from the database format to the row format.
     *
     * @param mixed $value
     * @param ColumnDataType $type
     * @return mixed
     */
    public function dbValueToRowValue(mixed $value, ColumnDataType $type): mixed;

}