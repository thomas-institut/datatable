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

namespace ThomasInstitut\DataTable\ReferenceTests;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use RuntimeException;
use ThomasInstitut\DataTable\DataTable;
use ThomasInstitut\DataTable\Exception\InvalidRowForUpdate;
use ThomasInstitut\DataTable\Exception\InvalidWhereClauseException;
use ThomasInstitut\DataTable\Exception\LastInsertIdNotAvailableException;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\Exception\RowDoesNotExist;
use ThomasInstitut\DataTable\PdoDataTable;
use ThomasInstitut\DataTable\PdoProvider\PdoProvider;
use ThomasInstitut\DataTable\PdoProvider\SimplePdoProvider;


/**
 * Reference test cases for PdoDataTable implementations.
 *
 * Extends DataTableReferenceTestCase with PDO-specific tests that apply
 * to any SQL dialect. Subclasses must provide dialect-specific setup
 * (DB creation, DDL, PDO connections) via abstract methods.
 */
abstract class PdoDataTableReferenceTestCase extends DataTableReferenceTestCase
{

    /**
     * Returns the table name used for standard tests.
     */
    abstract protected function getTableName(): string;

    /**
     * Returns the name of the first "bad" table (ID column has wrong type).
     */
    abstract protected function getBadTableName1(): string;

    /**
     * Returns the name of the second "bad" table (missing ID column).
     */
    abstract protected function getBadTableName2(): string;

    /**
     * Returns the ID column name used in test tables.
     */
    abstract protected function getIdColumnName(): string;

    /**
     * Construct a PdoDataTable for the standard test table.
     */
    abstract protected function constructPdoDataTable(PDO $pdo): PdoDataTable;

    /**
     * Construct a PdoDataTable using a PdoProvider (for mocking).
     */
    abstract protected function constructPdoDataTableWithProvider(PdoProvider $provider): PdoDataTable;

    /**
     * Construct a PdoDataTable for an arbitrary table name.
     */
    abstract protected function constructPdoDataTableForTable(PDO|PdoProvider $pdoOrProvider, string $tableName): PdoDataTable;

    /**
     * Get a full-privilege PDO connection.
     */
    abstract protected function getPdo(): PDO;

    /**
     * Get a read-only/restricted PDO connection.
     */
    abstract protected function getRestrictedPdo(): PDO;

    /**
     * Get a PdoDataTable with restricted permissions.
     */
    abstract protected function getRestrictedDt(): PdoDataTable;

    /**
     * Reset/create the test table with dialect-specific DDL.
     */
    abstract protected function resetTestDb(PDO $pdo, bool $autoInc = false): void;

    /**
     * Create malformed tables for error-path testing.
     */
    abstract protected function resetTestDbWithBadTables(PDO $pdo): void;

    /**
     * Return mock column info matching the dialect's format.
     *
     * For MySqlDialect this would be ['Type' => 'int'].
     */
    abstract protected function getMockColumnInfoResponse(): array;

    abstract public function getTestDataTable(bool $resetTable = true, bool $newSession = false): PdoDataTable;


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
        } catch (LastInsertIdNotAvailableException) {
            // should not happen here
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
        $this->assertEquals($rowId, $rows->getFirst()[$this->getIdColumnName()]);

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
        $dataTable = $this->constructPdoDataTableWithProvider($provider);

        $rowId = 101;
        $row = [$this->getIdColumnName() => $rowId, self::STRING_COLUMN => 'test'];
        $dataTable->createRow($row);

        $this->assertTrue($dataTable->rowExists($rowId));
        $this->assertEquals('test', $dataTable->getRow($rowId)[self::STRING_COLUMN]);
    }

    #[Test]
    public function testEscaping(): void
    {
        parent::testEscaping();

        $pdo = $this->getPdo();
        $dataTable = $this->constructPdoDataTable($pdo);

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
            $this->constructPdoDataTableForTable($pdo, $this->getBadTableName1());
        } catch (RuntimeException $exception) {
            $exceptionCaught = true;
            $errorCode = $exception->getCode();
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(PdoDataTable::ERROR_WRONG_COLUMN_TYPE, $errorCode);


        $exceptionCaught = false;
        $errorCode = -1;
        try {
            $this->constructPdoDataTableForTable($pdo, $this->getBadTableName2());
        } catch (RuntimeException $exception) {
            $exceptionCaught = true;
            $errorCode = $exception->getCode();
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(PdoDataTable::ERROR_REQUIRED_COLUMN_NOT_FOUND, $errorCode);


        $exceptionCaught = false;
        $errorCode = -1;
        try {
            $this->constructPdoDataTableForTable($pdo, 'non_existent_table');
        } catch (RuntimeException $exception) {
            $exceptionCaught = true;
            $errorCode = $exception->getCode();
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(PdoDataTable::ERROR_TABLE_NOT_FOUND, $errorCode);
    }

    /**
     * @throws RowAlreadyExists
     */
    #[Test]
    public function testUpdateRow(): void
    {
        parent::testUpdateRow();

        $pdo = $this->getPdo();
        $dataTable = $this->constructPdoDataTable($pdo);

        // INT_COLUMN should be an int
        $exceptionCaught = false;
        try {
            $dataTable->updateRow([$this->getIdColumnName() => 1, self::INT_COLUMN => 'bad']);
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
            $dataTable->updateRow([$this->getIdColumnName() => 1, self::STRING_COLUMN_2 => null]);
        } catch (RuntimeException) {
            $exceptionCaught = true;
        } catch (\Throwable $e) {
            $this->fail('Unexpected exception thrown: ' . $e->getMessage());
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
            $r = $dataTable->select('*', $this->getIdColumnName() . '=1', 0, $this->getIdColumnName() . ' ASC', 'testSelect2');

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
        $dt1 = $this->constructPdoDataTable($pdo);
        $dt2 = $this->constructPdoDataTable($pdo);

        $this->assertTrue($dt1->startTransaction());
        $this->assertFalse($dt2->startTransaction());
        $this->assertEquals(PdoDataTable::ERROR_MYSQL_ALREADY_IN_TRANSACTION, $dt2->getErrorCode());

        $this->assertTrue($dt1->commit());
    }

    /**
     * @throws Exception
     */
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
        $stmt->method('fetch')->willReturn($this->getMockColumnInfoResponse());
        $pdo->method('query')->willReturn($stmt);

        $dataTable = $this->constructPdoDataTableWithProvider($pdoProvider);

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
        $dataTable = $this->constructPdoDataTableWithProvider($pdoProvider);

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
        $dataTable = $this->constructPdoDataTableWithProvider($pdoProvider);

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
        $dataTable = $this->constructPdoDataTableWithProvider($pdoProvider);

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
        $dataTable = $this->constructPdoDataTableWithProvider($pdoProvider);

        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('inTransaction')->willReturnOnConsecutiveCalls(false, false);
        $this->assertTrue($dataTable->startTransaction());
        $pdo->method('rollBack')->willReturn(false);
        $this->assertFalse($dataTable->rollBack());
        $this->assertEquals(PdoDataTable::ERROR_MYSQL_COULD_NOT_ROLLBACK, $dataTable->getErrorCode());
        $this->assertStringContainsString('transaction ended', $dataTable->getErrorMessage());
    }
}
