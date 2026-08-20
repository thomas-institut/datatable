<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable\SqlDialect;


use PDO;
use RuntimeException;
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\DataTable\MySqlDataTable;
use ThomasInstitut\DataTable\MySqlDataTableWithSchema;
use ThomasInstitut\DataTable\MySqlUnitemporalDataTable;
use ThomasInstitut\DataTable\MySqlUnitemporalDataTableWithSchema;
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\DataTableSchema;
use ThomasInstitut\DataTable\Schema\MySqlRowValueTranslator;
use Throwable;

/**
 * Various operations on database tables for MySQL.
 */
class MySqlDbOperations
{


    private const array SupportedClasses = [
        MySqlDataTable::class,
        MySqlUnitemporalDataTable::class,
        MySqlDataTableWithSchema::class,
        MySqlUnitemporalDataTableWithSchema::class,
    ];

    /**
     * Attempts to create a table in the database using the provided PDO connection, table name, schema, and optional ifNotExists flag.
     *
     * **WARNING**: At this point this method is intended for internal testing purposes. It may not be suitable for production use.
     * @throws InvalidArgumentException
     */
    public static function createTableInDatabase(string $className, PDO $pdo, string $tableName, DataTableSchema $schema, bool $ifNotExists = false): void
    {
        if (!in_array($className, self::SupportedClasses, true)) {
            throw new InvalidArgumentException("Unsupported class $className");
        }

        $ifNotExistsSql = $ifNotExists ? 'IF NOT EXISTS' : '';
        $tableRowsSql = self::getCreateTableSql($className, $schema);
        $sql = "CREATE TABLE $ifNotExistsSql $tableName ($tableRowsSql)";
        try {
            $result = $pdo->exec($sql);
            if ($result === false) {
                throw new RuntimeException("Failed to create table $tableName: " . implode('.', $pdo->errorInfo()));
            }
        } catch (Throwable $e) { // @codeCoverageIgnore
            throw new RuntimeException("Failed to create table $tableName: " . implode('.', $pdo->errorInfo()) . ", sql = " . $sql, 0, $e); // @codeCoverageIgnore
        }
    }

    private static function getCreateTableSql(string $className, DataTableSchema $schema): string
    {
        $rowSqlSpecs = [];

        $valueTranslator = new MySqlRowValueTranslator();

        foreach ($schema->columnDefinitions as $colDef) {
            $dbColName = $colDef->dbColumn ?? $colDef->rowKey;
            $notNullSql = $colDef->nullable ? '' : 'NOT NULL';
            $defaultValueSql = '';
            if (!$colDef->required && !in_array($colDef->type, ColumnDataType::NoDefaultTypes)) {
                $defValue = $valueTranslator->rowValueToDbValue($colDef->defaultValue, $colDef->type);

                if (is_null($defValue)) {
                    $defaultValueSql = "DEFAULT NULL";
                } elseif (!is_string($defValue)) {
                    $defaultValueSql = "DEFAULT ($defValue)";
                } else {
                    $defaultValueSql = "DEFAULT '$defValue'";
                }
            }
            $idColumnSql = "$dbColName BIGINT AUTO_INCREMENT PRIMARY KEY NOT NULL";

            if (in_array($className, [ MySqlUnitemporalDataTable::class, MySqlUnitemporalDataTableWithSchema::class])) {
                $idColumnSql = "$dbColName BIGINT NOT NULL";
            }

            $rowSqlSpec = match ($colDef->type) {
                ColumnDataType::Id => $idColumnSql,
                ColumnDataType::Text => "$dbColName TEXT",
                ColumnDataType::VarChar => "$dbColName VARCHAR($colDef->typeLength)",
                ColumnDataType::Integer => "$dbColName INT",
                ColumnDataType::Serializable => "$dbColName LONGTEXT",
                ColumnDataType::Boolean => "$dbColName BOOLEAN",
                ColumnDataType::TimeString => "$dbColName DATETIME(6)",
                ColumnDataType::ValidFrom => "$dbColName datetime(6) NOT NULL DEFAULT '2020-04-09 00:00:00.000000'",
                ColumnDataType::ValidUntil => "$dbColName datetime(6) NOT NULL DEFAULT '9999-12-31 23:59:59.999999'"
            };

            if (!in_array($colDef->type, [ ColumnDataType::Id, ColumnDataType::ValidFrom, ColumnDataType::ValidUntil] )){
                if ($notNullSql !== '') {
                    $rowSqlSpec .= " $notNullSql";
                }
                if ($defaultValueSql !== '') {
                    $rowSqlSpec .= " $defaultValueSql";
                }
            }

            $rowSqlSpecs[] = $rowSqlSpec;
        }

        return implode(', ', $rowSqlSpecs);
    }


}