<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable\SqlDialect;

use PDOException;

interface SqlDialect
{
    public function getName(): string;

    public function quoteIdentifier(string $identifier): string;

    public function getTableColumnInfoQuery(string $tableName, string $columnName): string;

    public function isTableNotFoundException(PDOException $e): bool;

    /**
     * @param array<string, mixed> $columnInfo
     */
    public function getColumnType(array $columnInfo): string;

    public function matchesRequiredType(string $columnType, string $requiredType): bool;

    public function getTableStatusQuery(string $tableName): string;

    /**
     * @param array<string, mixed> $tableInfo
     */
    public function tableSupportsTransactions(array $tableInfo): bool;

    public function isSearchErrorRecoverable(PDOException $e): bool;
}
