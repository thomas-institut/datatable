<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable\Schema;

use ThomasInstitut\DataTable\Exception\InvalidRow;

interface RowTranslator
{

    /**
     * Returns a database row from an input row
     *
     * @param array<string, mixed> $inputRow
     * @param bool $failOnMissingRequired if false, missing required fields are ignored
     * @return array<string, mixed>
     * @throws InvalidRow
     */
    public function inputRowToDb(array $inputRow, bool $failOnMissingRequired = true): array;

    /**
     * @param array<string, mixed> $dbRow
     * @return array<string, mixed>
     */
    public function dbRowToOutputRow(array $dbRow): array;
}
