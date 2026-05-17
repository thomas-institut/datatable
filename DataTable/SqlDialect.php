<?php

namespace ThomasInstitut\DataTable;

use PDOException;

interface SqlDialect
{
    public function getName(): string;

    public function quoteIdentifier(string $identifier): string;

    public function getTableColumnInfoQuery(string $tableName, string $columnName): string;

    public function isTableNotFoundException(PDOException $e): bool;

    public function getColumnType(array $columnInfo): string;

    public function matchesRequiredType(string $columnType, string $requiredType): bool;

    public function getTableStatusQuery(string $tableName): string;

    public function tableSupportsTransactions(array $tableInfo): bool;

    public function isSearchErrorRecoverable(PDOException $e): bool;
}
