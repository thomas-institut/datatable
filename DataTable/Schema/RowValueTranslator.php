<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable\Schema;

interface RowValueTranslator
{

    /**
     * Translates a value from the row format to the database format.
     */
    public function rowValueToDbValue(mixed $value, ColumnDataType $type): mixed;

    /**
     * Translates a value from the database format to the row format.
     */
    public function dbValueToRowValue(mixed $value, ColumnDataType $type): mixed;

}
