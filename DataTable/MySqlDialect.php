<?php

namespace ThomasInstitut\DataTable;

use PDOException;

class MySqlDialect implements SqlDialect
{
    public function getName(): string
    {
        return 'MySql';
    }

    public function quoteIdentifier(string $identifier): string
    {
        $safeIdentifier = str_replace('`', '``', $identifier);
        return "`$safeIdentifier`";
    }

    public function getTableColumnInfoQuery(string $tableName, string $columnName): string
    {
        return 'SHOW COLUMNS FROM ' . $tableName . ' LIKE ' . "'$columnName'";
    }

    public function isTableNotFoundException(PDOException $e): bool
    {
        return $e->getCode() === '42S02';
    }

    public function getColumnType(array $columnInfo): string
    {
        if (!isset($columnInfo['Type']) || !is_string($columnInfo['Type'])) {
            return '';
        }
        return $columnInfo['Type'];
    }

    public function matchesRequiredType(string $columnType, string $requiredType): bool
    {
        $preg = '/^' . $requiredType . '/';
        return preg_match($preg, $columnType) === 1;
    }

    public function getTableStatusQuery(string $tableName): string
    {
        return "SHOW TABLE STATUS WHERE Name='$tableName'";
    }

    public function tableSupportsTransactions(array $tableInfo): bool
    {
        return $tableInfo['Engine'] === 'InnoDB' ?? false;
    }

    public function isSearchErrorRecoverable(PDOException $e): bool
    {
        return $e->getCode() === '42000' || $e->getCode() === '42S22';
    }
}
