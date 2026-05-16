<?php

/*
 * The MIT License
 *
 * Copyright 2017 Rafael Nájera <rafael@najera.ca>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace ThomasInstitut\DataTable;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

#[CoversClass(MySqlDataTable::class)]
class MySqlDataTableTest extends DataTableReferenceTestCase
{

    public int $numRows = 100;

    const string DB = 'dt';
    const string TABLE_NAME = 'dt_test_table';
    const string BAD_TABLE_NAME_1 = 'bad_table_1';
    const string BAD_TABLE_NAME_2 = 'bad_table_2';

    const string ID_COLUMN_NAME = 'row_id';
    static private ?PDO $motherSession = null;

    static private int $pdoCount = 0;


    public static function setUpBeforeClass(): void
    {
        // Use a temporary PDO connection with root privileges to create the user
        $db = self::DB;
        $dsn = "mysql:dbname=$db;host=mysql";
        $pdo = new PDO($dsn, 'root', 'root');
        $createUserSQL = "CREATE USER IF NOT EXISTS 'restricted'@'%' IDENTIFIED BY 'restricted';";
        $grantPrivilegesSQL = "GRANT SELECT ON `$db`.* TO 'restricted'@'%';";
        $pdo->exec($createUserSQL);
        $pdo->exec($grantPrivilegesSQL);
    }

    public function multipleDataAccessSessionsAvailable(): bool
    {
        return true;
    }

    protected function constructMySqlDataTable(PDO $pdo): MySqlDataTable
    {
        return new MySqlDataTable($pdo, self::TABLE_NAME, false, self::ID_COLUMN_NAME);
    }

    protected function getLoggerNamePrefix(): string
    {
        return 'MySqlDataTable';
    }

    public function getTestDataTable(bool $resetTable = true, bool $newSession = false): MySqlDataTable
    {
        if (self::$motherSession === null) {
            self::$motherSession = $this->getPdo();
            $pdo = self::$motherSession;
            self::$pdoCount = 1;
        } else {
            if ($newSession) {
                $pdo = $this->getPdo();
                self::$pdoCount++;
            } else {
                $pdo = self::$motherSession;
            }
        }

        if ($resetTable) {
            $this->resetTestDb(self::$motherSession);
        }

        return $this->constructMySqlDataTable($pdo);
    }

    public function getRestrictedDt(): MySqlDataTable
    {
        $restrictedPdo = $this->getRestrictedPdo();
        return new MySqlDataTable($restrictedPdo, self::TABLE_NAME, false, self::ID_COLUMN_NAME);
    }

    public function getPdo(): PDO
    {
        $db = self::DB;
        $dsn = "mysql:dbname=$db;host=mysql";
        return new PDO($dsn, 'root', 'root');
    }

    public function getRestrictedPdo(): PDO
    {
        $db = self::DB;
        $dsn = "mysql:dbname=$db;host=mysql";
        return new PDO($dsn, 'restricted', 'restricted');
    }

    public function resetTestDb(PDO $pdo, bool $autoInc = false): void
    {
        $idCol = self::ID_COLUMN_NAME;
        $intCol = self::INT_COLUMN;
        $stringCol = self::STRING_COLUMN;
        $otherStringCol = self::STRING_COLUMN_2;
        $testTableName = self::TABLE_NAME;

        $autoIncrement = $autoInc ? 'AUTO_INCREMENT' : '';

        $tableSetupSQL = <<<EOD
            DROP TABLE IF EXISTS `$testTableName`;
            CREATE TABLE IF NOT EXISTS `$testTableName` (
              $idCol int(11) UNSIGNED NOT NULL $autoIncrement,
              $intCol int(11) DEFAULT NULL,
              $stringCol varchar(100) DEFAULT NULL,
              $otherStringCol varchar(100) DEFAULT NULL,
              PRIMARY KEY (`$idCol`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
EOD;
        $pdo->query($tableSetupSQL);
    }

    public function resetTestDbWithBadTables(PDO $pdo): void
    {
        $idCol = self::ID_COLUMN_NAME;
        $intCol = self::INT_COLUMN;
        $stringCol = self::STRING_COLUMN;

        $badTableName1 = self::BAD_TABLE_NAME_1;
        $badTableName2 = self::BAD_TABLE_NAME_2;


        $tableSetupSQL = <<<EOD
            DROP TABLE IF EXISTS `$badTableName1`;
            CREATE TABLE IF NOT EXISTS `$badTableName1` (
              $idCol varchar(100) NOT NULL,
              $intCol int(11) DEFAULT NULL,
              $stringCol varchar(100) DEFAULT NULL,
              PRIMARY KEY ($idCol)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            DROP TABLE IF EXISTS `$badTableName2`;                
            CREATE TABLE IF NOT EXISTS `$badTableName2` (
              $intCol int(11) DEFAULT NULL,
              $stringCol varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
EOD;
        $pdo->query($tableSetupSQL);
    }


    /**
     * @throws RowAlreadyExists
     */
    #[Test]
    public function testRestrictedPdo(): void
    {
        $dataTable = $this->getTestDataTable();
        $restrictedDataTable = $this->getRestrictedDt();

        $stringCol = self::STRING_COLUMN;

        $exceptionCaught = false;
        try {
            $restrictedDataTable->createRow([$stringCol => 25]);
        } catch (RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(MySqlDataTable::ERROR_MYSQL_QUERY_ERROR, $restrictedDataTable->getErrorCode());

        $rowId = $dataTable->createRow([$stringCol => 25]);
        $this->assertNotFalse($rowId);
        $this->assertEquals(DataTable::ERROR_NO_ERROR, $dataTable->getErrorCode());

        $exceptionCaught = false;
        try {
            $restrictedDataTable->deleteRow($rowId);
        } catch (RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(MySqlDataTable::ERROR_MYSQL_QUERY_ERROR, $restrictedDataTable->getErrorCode());


        $rows = $restrictedDataTable->getAllRows();
        $this->assertEquals(1, $rows->count());
        $this->assertEquals($rowId, $rows->getFirst()[self::ID_COLUMN_NAME]);

        $result = $restrictedDataTable->rowExists($rowId);
        $this->assertTrue($result);
    }

    #[Test]
    public function testEscaping(): void
    {
        parent::testEscaping();

        $pdo = $this->getPdo();
        $dataTable = new MySqlDataTable($pdo, self::TABLE_NAME, false, self::ID_COLUMN_NAME);

        $exceptionCaught = false;
        try {
            $dataTable->createRow([self::INT_COLUMN => 'A string']);
        } catch (RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
    }

    #[Test]
    public function testBadTables(): void
    {
        $pdo = $this->getPdo();
        $this->resetTestDbWithBadTables($pdo);
        $exceptionCaught = false;
        $errorCode = -1;
        try {
            new MySqlDataTable($pdo, self::BAD_TABLE_NAME_1, false, self::ID_COLUMN_NAME);
        } catch (RuntimeException $exception) {
            $exceptionCaught = true;
            $errorCode = $exception->getCode();
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(MySqlDataTable::ERROR_WRONG_COLUMN_TYPE, $errorCode);


        $exceptionCaught = false;
        $errorCode = -1;
        try {
            new MySqlDataTable($pdo, self::BAD_TABLE_NAME_2, false, self::ID_COLUMN_NAME);
        } catch (RuntimeException $exception) {
            $exceptionCaught = true;
            $errorCode = $exception->getCode();
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(MySqlDataTable::ERROR_REQUIRED_COLUMN_NOT_FOUND, $errorCode);


        $exceptionCaught = false;
        $errorCode = -1;
        try {
            new MySqlDataTable($pdo, 'non_existent_table', false, self::ID_COLUMN_NAME);
        } catch (RuntimeException $exception) {
            $exceptionCaught = true;
            $errorCode = $exception->getCode();
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(MySqlDataTable::ERROR_TABLE_NOT_FOUND, $errorCode);
    }

    #[Test]
    public function testUpdateRow(): void
    {
        parent::testUpdateRow();

        $pdo = $this->getPdo();
        $dataTable = new MySqlDataTable($pdo, self::TABLE_NAME, false, self::ID_COLUMN_NAME);


        // INT_COLUMN should be an int
        $exceptionCaught = false;
        try {
            $dataTable->updateRow([self::ID_COLUMN_NAME => 1, self::INT_COLUMN => 'bad']);
        } catch (RuntimeException|InvalidRowForUpdate|RowDoesNotExist) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(MySqlDataTable::ERROR_MYSQL_QUERY_ERROR,
            $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());

        // Null values are fine (because the table schema allows them)
        $exceptionCaught = false;
        try {
            $dataTable->updateRow([self::ID_COLUMN_NAME => 1, self::STRING_COLUMN_2 => null]);
        } catch (RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertFalse($exceptionCaught);
    }

    #[Test]
    public function testNonExistentRows(): void
    {
        parent::testNonExistentRows();

        $dataTable = $this->getTestDataTable();

        for ($i = 1; $i < 100; $i++) {
            $row = $dataTable->getRow($i);
            $this->assertNull($row);
            $this->assertEquals(DataTable::ERROR_ROW_DOES_NOT_EXIST,
                $dataTable->getErrorCode());
            $this->assertNotEquals('', $dataTable->getErrorMessage());
        }
    }

    #[Test]
    public function testSelect(): void
    {

        $dataTable = $this->getTestDataTable();

        $exceptionCaught = false;
        try {
            $dataTable->select('*', '', 0, '', 'testSelect');
        } catch (InvalidWhereClauseException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        try {
            $r = $dataTable->select('*', self::ID_COLUMN_NAME . '=1', 0, self::ID_COLUMN_NAME . ' ASC', 'testSelect2');

            $this->assertEquals(0, $r->rowCount());
        } catch (InvalidWhereClauseException) {

        }
    }


}
