<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable;

use PDO;
use RuntimeException;
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\PdoProvider\PdoProvider;
use ThomasInstitut\DataTable\PdoProvider\SimplePdoProvider;
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\ColumnDefArray;
use ThomasInstitut\DataTable\Schema\ColumnDefinition;
use ThomasInstitut\DataTable\Schema\DataTableSchema;
use ThomasInstitut\DataTable\SqlDialect\MySqlDbOperations;

final class MySqlUnitemporalDataTableWithSchemaTest extends ReferenceTests\UnitemporalDataTableWithSchemaReferenceTestCase
{
    /**
     * @var array<string>
     */
    private array $createdTableNames = [];

    private ?string $instancePrefix = null;

    private PdoProvider $pdoProvider;


    public function setUp(): void
    {
        parent::setUp();
        $this->instancePrefix = ToolBox::getRandomString(4);
        $this->pdoProvider = new SimplePdoProvider($this->getNewPdo());
    }

    public function tearDown(): void
    {
        parent::tearDown();
        foreach($this->createdTableNames as $tableName) {
            $this->pdoProvider->getPdo()->exec("DROP TABLE $tableName");
        }
    }

    private function getNewPdo(): PDO
    {
        $db = 'dt';
        $dsn = "mysql:dbname=$db;host=mysql";
        return new PDO($dsn, 'root', 'root');
    }

    private function getNewTableName(): string {
        return implode('_', ['udtws', $this->instancePrefix, count($this->createdTableNames)]);
    }

    /**
     * @throws InvalidColumnDefinitionsArray
     * @throws InvalidArgumentException
     */
    public function getUnitemporalTestTable(array $columnDefinitions): UnitemporalDataTableWithSchema
    {
        $tableName = $this->getNewTableName();
        $schema = new DataTableSchema($columnDefinitions);
        $pdo  = $this->pdoProvider->getPdo();
        $dbIdColumnName = $schema->getIdDbColumn() ?? throw new RuntimeException("Id column name not found in schema");
        $validFromDefs = ColumnDefArray::getColumnDefsForType($schema->columnDefinitions, ColumnDataType::ValidFrom);
        if ($validFromDefs === []) {
            $schema->columnDefinitions[] = new ColumnDefinition('valid_from', ColumnDataType::ValidFrom);
        }
        $validUntilDefs = ColumnDefArray::getColumnDefsForType($schema->columnDefinitions, ColumnDataType::ValidUntil);
        if ($validUntilDefs === []) {
            $schema->columnDefinitions[] = new ColumnDefinition('valid_until', ColumnDataType::ValidUntil);
        }
        if ($validFromDefs !== [] && $validUntilDefs !== []) {
            $validFromDbColName = $validFromDefs[0]->dbColumn ?? $validFromDefs[0]->rowKey;
            $validUntilDbColName = $validUntilDefs[0]->dbColumn ?? $validUntilDefs[0]->rowKey;
        } else {
            $validFromDbColName = 'valid_from';
            $validUntilDbColName = 'valid_until';
        }


        $pdo->exec("DROP TABLE IF EXISTS $tableName");
        MySqlDbOperations::createTableInDatabase(MySqlUnitemporalDataTableWithSchema::class, $pdo, $tableName, $schema);
        $this->createdTableNames[] = $tableName;
        $mySqlDataTable = new MySqlUnitemporalDataTable($this->pdoProvider, $tableName, $dbIdColumnName, $validFromDbColName, $validUntilDbColName);
        return new MySqlUnitemporalDataTableWithSchema($mySqlDataTable, $schema);
    }


}
