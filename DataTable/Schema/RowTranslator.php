<?php

namespace ThomasInstitut\DataTable\Schema;

use ThomasInstitut\DataTable\Exception\InvalidRow;

interface RowTranslator
{

    /**
     * Returns a database row from an input row
     *
     * @param bool $failOnMissingRequired if false, missing required fields are ignored
     * @throws InvalidRow
     */
    public function inputRowToDb(array $inputRow, bool $failOnMissingRequired = true): array;

    public function dbRowToOutputRow(array $dbRow): array;
}