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
use PDOStatement;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use ThomasInstitut\DataTable\Exception\InvalidRowForUpdate;
use ThomasInstitut\DataTable\Exception\InvalidWhereClauseException;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\Exception\RowDoesNotExist;
use ThomasInstitut\DataTable\PdoProvider\PdoProvider;
use ThomasInstitut\DataTable\PdoProvider\SimplePdoProvider;
use ThomasInstitut\DataTable\ReferenceTests\DataTableReferenceTestCase;

// TODO: change this into a PdoDataTable reference test and make MySqlDataTableTest a subclass just setting up a MySql table.


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

    protected function constructMySqlDataTable(PDO $pdo): PdoDataTable
    {
        return new MySqlDataTable($pdo, self::TABLE_NAME, false, self::ID_COLUMN_NAME);
    }

    protected function getLoggerNamePrefix(): string
    {
        return 'MySqlDataTable';
    }

    public function getTestDataTable(bool $resetTable = true, bool $newSession = false): PdoDataTable
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

    public function getRestrictedDt(): PdoDataTable
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
        $this->assertEquals(PdoDataTable::ERROR_MYSQL_QUERY_ERROR, $restrictedDataTable->getErrorCode());

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
        $this->assertEquals(PdoDataTable::ERROR_MYSQL_QUERY_ERROR, $restrictedDataTable->getErrorCode());


        $rows = $restrictedDataTable->getAllRows();
        $this->assertEquals(1, $rows->count());
        $this->assertEquals($rowId, $rows->getFirst()[self::ID_COLUMN_NAME]);

        $result = $restrictedDataTable->rowExists($rowId);
        $this->assertTrue($result);
    }

    /**
     * @throws RowAlreadyExists
     */
    #[Test]
    public function testDbConnectionProvider(): void
    {
        $pdo = $this->getPdo();
        $provider = new SimplePdoProvider($pdo);
        $dataTable = new MySqlDataTable($provider, self::TABLE_NAME, false, self::ID_COLUMN_NAME);

        $rowId = 101;
        $row = [self::ID_COLUMN_NAME => $rowId, self::STRING_COLUMN => 'test'];
        $dataTable->createRow($row);

        $this->assertTrue($dataTable->rowExists($rowId));
        $this->assertEquals('test', $dataTable->getRow($rowId)[self::STRING_COLUMN]);
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
        $this->assertEquals(PdoDataTable::ERROR_WRONG_COLUMN_TYPE, $errorCode);


        $exceptionCaught = false;
        $errorCode = -1;
        try {
            new MySqlDataTable($pdo, self::BAD_TABLE_NAME_2, false, self::ID_COLUMN_NAME);
        } catch (RuntimeException $exception) {
            $exceptionCaught = true;
            $errorCode = $exception->getCode();
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(PdoDataTable::ERROR_REQUIRED_COLUMN_NOT_FOUND, $errorCode);


        $exceptionCaught = false;
        $errorCode = -1;
        try {
            new MySqlDataTable($pdo, 'non_existent_table', false, self::ID_COLUMN_NAME);
        } catch (RuntimeException $exception) {
            $exceptionCaught = true;
            $errorCode = $exception->getCode();
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(PdoDataTable::ERROR_TABLE_NOT_FOUND, $errorCode);
    }

    /**
     * @throws RowAlreadyExists
     * @throws RowDoesNotExist
     * @throws InvalidRowForUpdate
     */
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
        $this->assertEquals(PdoDataTable::ERROR_MYSQL_QUERY_ERROR,
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


    #[Test]
    public function testTransactionErrors(): void
    {
        $dataTable = $this->getTestDataTable();
        if (!$dataTable->supportsTransactions()) {
            $this->markTestSkipped('Database does not support transactions');
        }

        // Test startTransaction when already in transaction
        $this->assertTrue($dataTable->startTransaction());
        $this->assertFalse($dataTable->startTransaction());
        $this->assertEquals(PdoDataTable::ERROR_TABLE_ALREADY_IN_TRANSACTION, $dataTable->getErrorCode());

        $this->assertTrue($dataTable->commit());

        // Test commit when not in transaction
        $this->assertFalse($dataTable->commit());
        $this->assertEquals(PdoDataTable::ERROR_TABLE_NOT_IN_TRANSACTION, $dataTable->getErrorCode());

        // Test rollBack when not in transaction
        $this->assertFalse($dataTable->rollBack());
        $this->assertEquals(PdoDataTable::ERROR_TABLE_NOT_IN_TRANSACTION, $dataTable->getErrorCode());

        // Test startTransaction when underlying PDO is already in transaction
        $pdo = $this->getPdo();
        $dt1 = $this->constructMySqlDataTable($pdo);
        $dt2 = $this->constructMySqlDataTable($pdo);

        $this->assertTrue($dt1->startTransaction());
        $this->assertFalse($dt2->startTransaction());
        $this->assertEquals(PdoDataTable::ERROR_MYSQL_ALREADY_IN_TRANSACTION, $dt2->getErrorCode());

        $this->assertTrue($dt1->commit());
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function testTransactionFailures(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdoProvider = $this->createStub(PdoProvider::class);
        $pdoProvider->method('getPdo')->willReturn($pdo);

        // Mock column check
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('rowCount')->willReturn(1);
        $stmt->method('fetch')->willReturn(['Type' => 'int']);
        $pdo->method('query')->willReturn($stmt);

        $dataTable = new MySqlDataTable($pdoProvider, self::TABLE_NAME, false, self::ID_COLUMN_NAME);

        // Test startTransaction failure
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('beginTransaction')->willReturn(false);
        $this->assertFalse($dataTable->startTransaction());
        $this->assertEquals(PdoDataTable::ERROR_MYSQL_COULD_NOT_BEGIN_TRANSACTION, $dataTable->getErrorCode());

        // Test commit failure
        $pdo = $this->createStub(PDO::class); // Fresh stub for fresh state
        $pdo->method('query')->willReturn($stmt);
        $pdoProvider = $this->createStub(PdoProvider::class);
        $pdoProvider->method('getPdo')->willReturn($pdo);
        $dataTable = new MySqlDataTable($pdoProvider, self::TABLE_NAME, false, self::ID_COLUMN_NAME);

        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('inTransaction')->willReturnOnConsecutiveCalls(false, true);
        $this->assertTrue($dataTable->startTransaction());
        $pdo->method('commit')->willReturn(false);
        $this->assertFalse($dataTable->commit());
        $this->assertEquals(PdoDataTable::ERROR_MYSQL_COULD_NOT_COMMIT, $dataTable->getErrorCode());
        $this->assertStringContainsString('table still in a transaction', $dataTable->getErrorMessage());

        // Test commit failure where transaction ended
        $pdo = $this->createStub(PDO::class); // Fresh stub for fresh state
        $pdo->method('query')->willReturn($stmt);
        $pdoProvider = $this->createStub(PdoProvider::class);
        $pdoProvider->method('getPdo')->willReturn($pdo);
        $dataTable = new MySqlDataTable($pdoProvider, self::TABLE_NAME, false, self::ID_COLUMN_NAME);

        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('inTransaction')->willReturnOnConsecutiveCalls(false, false);
        $this->assertTrue($dataTable->startTransaction());
        $pdo->method('commit')->willReturn(false);
        $this->assertFalse($dataTable->commit());
        $this->assertEquals(PdoDataTable::ERROR_MYSQL_COULD_NOT_COMMIT, $dataTable->getErrorCode());
        $this->assertStringContainsString('transaction ended', $dataTable->getErrorMessage());

        // Test rollBack failure
        $pdo = $this->createStub(PDO::class); // Fresh stub for fresh state
        $pdo->method('query')->willReturn($stmt);
        $pdoProvider = $this->createStub(PdoProvider::class);
        $pdoProvider->method('getPdo')->willReturn($pdo);
        $dataTable = new MySqlDataTable($pdoProvider, self::TABLE_NAME, false, self::ID_COLUMN_NAME);

        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('inTransaction')->willReturnOnConsecutiveCalls(false, true);
        $this->assertTrue($dataTable->startTransaction());
        $pdo->method('rollBack')->willReturn(false);
        $this->assertFalse($dataTable->rollBack());
        $this->assertEquals(PdoDataTable::ERROR_MYSQL_COULD_NOT_ROLLBACK, $dataTable->getErrorCode());
        $this->assertStringContainsString('table still in a transaction', $dataTable->getErrorMessage());

        // Test rollBack failure where transaction ended
        $pdo = $this->createStub(PDO::class); // Fresh stub for fresh state
        $pdo->method('query')->willReturn($stmt);
        $pdoProvider = $this->createStub(PdoProvider::class);
        $pdoProvider->method('getPdo')->willReturn($pdo);
        $dataTable = new MySqlDataTable($pdoProvider, self::TABLE_NAME, false, self::ID_COLUMN_NAME);

        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('inTransaction')->willReturnOnConsecutiveCalls(false, false);
        $this->assertTrue($dataTable->startTransaction());
        $pdo->method('rollBack')->willReturn(false);
        $this->assertFalse($dataTable->rollBack());
        $this->assertEquals(PdoDataTable::ERROR_MYSQL_COULD_NOT_ROLLBACK, $dataTable->getErrorCode());
        $this->assertStringContainsString('transaction ended', $dataTable->getErrorMessage());
    }
}
