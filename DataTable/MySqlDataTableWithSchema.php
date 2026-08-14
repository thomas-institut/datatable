<?php

namespace ThomasInstitut\DataTable;


use PDO;
use RuntimeException;
use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\DataTableSchema;
use ThomasInstitut\DataTable\Schema\MySqlRowValueTranslator;
use ThomasInstitut\DataTable\Schema\SupportedSearchCondition;
use Throwable;


class MySqlDataTableWithSchema extends GenericDataTableWithSchema
{

    /**
     * Constructs a new instance of MySqlDataTableWithSchema based on a MySqlDataTable and a DataTableSchema.
     *
     * The MySqlDataTable must be already created in the database with a schema that matches the provided DataTableSchema.
     *
     * @throws InvalidColumnDefinitionsArray
     * @see MySqlDataTable::createTable()
     */
    public function __construct(private readonly MySqlDataTable $mySqlDataTable, private readonly DataTableSchema $dataTableSchema)
    {
        parent::__construct($this->mySqlDataTable, $this->dataTableSchema, new MySqlRowValueTranslator(), SupportedSearchCondition::reasonableDefaults(), ColumnDataType::cases());
    }


    /**
     * Attempts to create a table in the database using the provided PDO connection, table name, schema, and optional ifNotExists flag.
     *
     * **WARNING**: At this point this method is intended for internal testing purposes. It may not be suitable for production use.
     *
     * @param PDO $pdo
     * @param string $tableName
     * @param DataTableSchema $schema
     * @param bool $ifNotExists
     * @return void
     */
    public static function createTableInDatabase(PDO $pdo, string $tableName, DataTableSchema $schema, bool $ifNotExists = false): void
    {
        $ifNotExistsSql = $ifNotExists ? 'IF NOT EXISTS' : '';
        $tableRowsSql = self::getCreateTableSql($schema);
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

    private static function getCreateTableSql(DataTableSchema $schema): string
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
                } else {
                    if (!is_string($defValue)) {
                        $defaultValueSql = "DEFAULT ($defValue)";
                    } else {
                        $defaultValueSql = "DEFAULT '$defValue'";
                    }
                }
            }
            $rowSqlSpec = match ($colDef->type) {
                ColumnDataType::Id => "$dbColName BIGINT AUTO_INCREMENT PRIMARY KEY NOT NULL",
                ColumnDataType::Text => "$dbColName TEXT",
                ColumnDataType::VarChar => "$dbColName VARCHAR({$colDef->typeLength})",
                ColumnDataType::Integer => "$dbColName INT",
                ColumnDataType::Serializable => "$dbColName LONGTEXT",
                ColumnDataType::Boolean => "$dbColName BOOLEAN",
            };

            if ($colDef->type !== ColumnDataType::Id) {
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