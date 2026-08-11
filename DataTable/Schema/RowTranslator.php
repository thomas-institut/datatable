<?php

namespace ThomasInstitut\DataTable\Schema;

interface RowTranslator
{

    /**
     * Returns a database row from an input row
     *
     * @param array $inputRow
     * @return array
     */
    public function inputRowToDb(array $inputRow): array;

    /**
     * @param array $dbRow
     * @return array
     */
    public function dbRowToOutputRow(array $dbRow): array;
}