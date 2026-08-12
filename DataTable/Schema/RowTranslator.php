<?php

namespace ThomasInstitut\DataTable\Schema;

use ThomasInstitut\DataTable\Exception\InvalidRow;

interface RowTranslator
{

    /**
     * Returns a database row from an input row
     *
     * @param array $inputRow
     * @param bool $failOnMissingRequired if false, missing required fields are ignored
     * @return array
     * @throws InvalidRow
     */
    public function inputRowToDb(array $inputRow, bool $failOnMissingRequired = true): array;

    /**
     * @param array $dbRow
     * @return array
     */
    public function dbRowToOutputRow(array $dbRow): array;
}