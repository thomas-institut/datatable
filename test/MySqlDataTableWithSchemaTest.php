<?php

namespace ThomasInstitut\DataTable;

use PDO;
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\PdoProvider\PdoProvider;
use ThomasInstitut\DataTable\PdoProvider\SimplePdoProvider;
use ThomasInstitut\DataTable\ReferenceTests\DataTableWithSchemaReferenceTestCase;
use ThomasInstitut\DataTable\Schema\DataTableSchema;

class MySqlDataTableWithSchemaTest extends DataTableWithSchemaReferenceTestCase
{

    /**
     * @var array<string>
     */
    private array $createdTableNames = [];

    private ?string $instancePrefix = null;

    private ?PdoProvider $pdoProvider = null;


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
        return implode('_', ['dtws', $this->instancePrefix, count($this->createdTableNames)]);
    }

    /**
     * @throws InvalidColumnDefinitionsArray
     */
    public function getTestTable(array $columnDefinitions): DataTableWithSchema
    {
        $tableName = $this->getNewTableName();
        $schema = new DataTableSchema($columnDefinitions);
        $pdo  = $this->pdoProvider->getPdo();
        $dbIdColumnName = $schema->getIdDbColumn();
        $pdo->exec("DROP TABLE IF EXISTS $tableName");
        MySqlDataTableWithSchema::createTableInDatabase($pdo, $tableName, $schema);
        $this->createdTableNames[] = $tableName;
        $mySqlDataTable = new MySqlDataTable($this->pdoProvider, $tableName, true, $dbIdColumnName);
        return new MySqlDataTableWithSchema($mySqlDataTable, new DataTableSchema($columnDefinitions));
    }


}