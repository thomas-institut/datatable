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

use ArrayIterator;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use ThomasInstitut\DataTable\DataTable;
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\DataTable\Exception\InvalidRowForUpdate;
use ThomasInstitut\DataTable\Exception\InvalidRowUpdateTime;
use ThomasInstitut\DataTable\Exception\InvalidSearchSpec;
use ThomasInstitut\DataTable\Exception\InvalidSearchType;
use ThomasInstitut\DataTable\Exception\InvalidTimeStringException;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\Exception\RowDoesNotExist;
use ThomasInstitut\DataTable\PdoDataTable;
use ThomasInstitut\DataTable\PdoProvider\PdoProvider;
use ThomasInstitut\DataTable\PdoProvider\SimplePdoProvider;
use ThomasInstitut\DataTable\PdoUnitemporalDataTable;
use ThomasInstitut\DataTable\UnitemporalDataTable;
use ThomasInstitut\TimeString\InvalidTimeZoneException;
use ThomasInstitut\TimeString\MalformedStringException;
use ThomasInstitut\TimeString\TimeString;


/**
 * Reference test cases for PdoUnitemporalDataTable implementations.
 *
 * Extends PdoDataTableReferenceTestCase with unitemporal-specific tests that apply
 * to any SQL dialect. Subclasses must provide dialect-specific setup
 * (DB creation, DDL, PDO connections) via abstract methods.
 */
abstract class PdoUnitemporalDataTableReferenceTestCase extends PdoDataTableReferenceTestCase
{

    /**
     * Construct a PdoUnitemporalDataTable for the standard test table.
     */
    abstract protected function constructPdoUnitemporalDataTable(PDO $pdo): PdoUnitemporalDataTable;

    /**
     * Construct a PdoUnitemporalDataTable for an arbitrary table name.
     */
    abstract protected function constructPdoUnitemporalDataTableForTable(PDO|PdoProvider $pdoOrProvider, string $tableName): PdoUnitemporalDataTable;

    protected function constructPdoDataTable(PDO $pdo): PdoDataTable
    {
        return $this->constructPdoUnitemporalDataTable($pdo);
    }

    protected function constructPdoDataTableWithProvider(PdoProvider $provider): PdoDataTable
    {
        return $this->constructPdoUnitemporalDataTableForTable($provider, $this->getTableName());
    }

    protected function constructPdoDataTableForTable(PDO|PdoProvider $pdoOrProvider, string $tableName): PdoDataTable
    {
        return $this->constructPdoUnitemporalDataTableForTable($pdoOrProvider, $tableName);
    }

    /**
     * Return mock column info matching the dialect's format for datetime columns.
     *
     * For MySqlDialect this would be ['Type' => 'datetime'].
     */
    abstract protected function getMockDatetimeColumnInfoResponse(): array;

    /**
     * Helper: create a mock PDOStatement that returns the given column info responses
     * in sequence (one per isTableColumnValid call).
     */
    private function createColumnCheckStmt(array ...$columnInfoResponses): PDOStatement
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('rowCount')->willReturn(1);
        $stmt->method('fetch')->willReturnOnConsecutiveCalls(...$columnInfoResponses);
        return $stmt;
    }

    /**
     * Helper: create a mock PDO that returns proper column info for unitemporal tables
     * (id column int + valid_from datetime + valid_until datetime) for each construction,
     * plus an additional prepare() stub for the rowExistsById statement.
     */
    private function createUnitemporalMockPdoAndProvider(): array
    {
        $intResp = $this->getMockColumnInfoResponse();
        $dtResp = $this->getMockDatetimeColumnInfoResponse();

        $pdo = $this->createStub(PDO::class);
        $pdoProvider = $this->createStub(PdoProvider::class);
        $pdoProvider->method('getPdo')->willReturn($pdo);

        // Each constructPdoDataTableWithProvider call triggers 3 query() calls
        // for column validation (id, valid_from, valid_until), plus a prepare() call.
        $stmt1 = $this->createColumnCheckStmt($intResp, $dtResp, $dtResp);
        $prepareStmt = $this->createStub(PDOStatement::class);
        $pdo->method('query')->willReturn($stmt1);
        $pdo->method('prepare')->willReturn($prepareStmt);

        return [$pdo, $pdoProvider];
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function testTransactionFailures(): void
    {
        $intResp = $this->getMockColumnInfoResponse();
        $dtResp = $this->getMockDatetimeColumnInfoResponse();

        // Helper to create a fresh mock PDO + provider for each sub-test
        $createMocks = function () use ($intResp, $dtResp): array {
            $pdo = $this->createStub(PDO::class);
            $pdoProvider = $this->createStub(PdoProvider::class);
            $pdoProvider->method('getPdo')->willReturn($pdo);

            $stmt = $this->createStub(PDOStatement::class);
            $stmt->method('rowCount')->willReturn(1);
            $stmt->method('fetch')->willReturnOnConsecutiveCalls($intResp, $dtResp, $dtResp);
            $pdo->method('query')->willReturn($stmt);

            $prepareStmt = $this->createStub(PDOStatement::class);
            $pdo->method('prepare')->willReturn($prepareStmt);

            return [$pdo, $pdoProvider];
        };

        // Test startTransaction failure
        [$pdo, $pdoProvider] = $createMocks();
        $dataTable = $this->constructPdoDataTableWithProvider($pdoProvider);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('beginTransaction')->willReturn(false);
        $this->assertFalse($dataTable->startTransaction());
        $this->assertEquals(PdoDataTable::ERROR_MYSQL_COULD_NOT_BEGIN_TRANSACTION, $dataTable->getErrorCode());

        // Test commit failure
        [$pdo, $pdoProvider] = $createMocks();
        $dataTable = $this->constructPdoDataTableWithProvider($pdoProvider);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('inTransaction')->willReturnOnConsecutiveCalls(false, true);
        $this->assertTrue($dataTable->startTransaction());
        $pdo->method('commit')->willReturn(false);
        $this->assertFalse($dataTable->commit());
        $this->assertEquals(PdoDataTable::ERROR_MYSQL_COULD_NOT_COMMIT, $dataTable->getErrorCode());
        $this->assertStringContainsString('table still in a transaction', $dataTable->getErrorMessage());

        // Test commit failure where transaction ended
        [$pdo, $pdoProvider] = $createMocks();
        $dataTable = $this->constructPdoDataTableWithProvider($pdoProvider);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('inTransaction')->willReturnOnConsecutiveCalls(false, false);
        $this->assertTrue($dataTable->startTransaction());
        $pdo->method('commit')->willReturn(false);
        $this->assertFalse($dataTable->commit());
        $this->assertEquals(PdoDataTable::ERROR_MYSQL_COULD_NOT_COMMIT, $dataTable->getErrorCode());
        $this->assertStringContainsString('transaction ended', $dataTable->getErrorMessage());

        // Test rollBack failure
        [$pdo, $pdoProvider] = $createMocks();
        $dataTable = $this->constructPdoDataTableWithProvider($pdoProvider);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('inTransaction')->willReturnOnConsecutiveCalls(false, true);
        $this->assertTrue($dataTable->startTransaction());
        $pdo->method('rollBack')->willReturn(false);
        $this->assertFalse($dataTable->rollBack());
        $this->assertEquals(PdoDataTable::ERROR_MYSQL_COULD_NOT_ROLLBACK, $dataTable->getErrorCode());
        $this->assertStringContainsString('table still in a transaction', $dataTable->getErrorMessage());

        // Test rollBack failure where transaction ended
        [$pdo, $pdoProvider] = $createMocks();
        $dataTable = $this->constructPdoDataTableWithProvider($pdoProvider);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('inTransaction')->willReturnOnConsecutiveCalls(false, false);
        $this->assertTrue($dataTable->startTransaction());
        $pdo->method('rollBack')->willReturn(false);
        $this->assertFalse($dataTable->rollBack());
        $this->assertEquals(PdoDataTable::ERROR_MYSQL_COULD_NOT_ROLLBACK, $dataTable->getErrorCode());
        $this->assertStringContainsString('transaction ended', $dataTable->getErrorMessage());
    }

    #[Test]
    public function testDbConnectionProvider(): void
    {
        $pdo = $this->getPdo();
        $provider = new SimplePdoProvider($pdo);
        $dataTable = $this->constructPdoUnitemporalDataTableForTable($provider, $this->getTableName());

        $rowId = 101;
        $row = [$this->getIdColumnName() => $rowId, self::STRING_COLUMN => 'test'];
        $dataTable->createRow($row);

        $this->assertTrue($dataTable->rowExists($rowId));
        $this->assertEquals('test', $dataTable->getRow($rowId)[self::STRING_COLUMN]);
    }

    #[Test]
    public function testBadTables(): void
    {

        $pdo = $this->getPdo();
        $this->resetTestDbWithBadTables($pdo);

        $exceptionCaught = false;
        try {
            $this->constructPdoUnitemporalDataTableForTable($pdo, 'test_table_bad_1');
        } catch(RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);


        $exceptionCaught = false;
        try {
            $this->constructPdoUnitemporalDataTableForTable($pdo, 'test_table_bad_2');
        } catch(RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            $this->constructPdoUnitemporalDataTableForTable($pdo, 'test_table_bad_3');
        } catch(RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            $this->constructPdoUnitemporalDataTableForTable($pdo, 'test_table_bad_4');
        } catch(RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            $this->constructPdoUnitemporalDataTableForTable($pdo, 'test_table_bad_5');
        } catch(RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            $this->constructPdoUnitemporalDataTableForTable($pdo, 'test_table_bad_6');
        } catch(RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            $this->constructPdoUnitemporalDataTableForTable($pdo, 'non_existent_table');
        } catch(RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
    }

    /**
     * @throws InvalidTimeStringException
     * @throws MalformedStringException
     * @throws RowDoesNotExist
     * @throws InvalidRowUpdateTime
     * @throws InvalidTimeZoneException
     * @throws RowAlreadyExists
     */
    #[Test]
    public function testFindRowsWithTime(): void
    {
        /** @var PdoUnitemporalDataTable $dataTable */
        $dataTable = $this->getTestDataTable();
        
        $timeZero = TimeString::fromVariable('2010-01-01');
        $times = [ '2014-01-01',
            '2015-01-01',
            '2016-01-01'];
       
        $nEntries = 10;
        $someInt = 1000;
        $nTimes = count($times);
        
        // Create different versions of $nEntries
        $ids = [];
        for ($i = 0; $i < $nEntries; $i++) {
            $rowId = $dataTable->createRowWithTime(
                [self::INT_COLUMN => $someInt],
                $timeZero
            );
            $ids[] = $rowId;
            $timesCount = 1;
            foreach ($times as $t) {
                $t = TimeString::fromVariable($t);
                $dataTable->realUpdateRowWithTime([$this->getIdColumnName() => $rowId,
                    self::STRING_COLUMN => 'Value' .
                    $timesCount++], $t);
            }
        }
        
        
        // Check latest versions
        foreach ($ids as $rowId) {
            $row = $dataTable->getRow($rowId);
            $this->assertNotNull($row);
            $this->assertEquals($someInt, $row[self::INT_COLUMN]);
            $this->assertEquals('Value' . $nTimes, $row[self::STRING_COLUMN]);
        }
        
        // Only the last versions should show up in these searches
        for($i = 1; $i < $nTimes; $i++) {
            $foundsRows = $dataTable->findRows([self::STRING_COLUMN => 'Value' . $i]);
            $this->assertEquals(0, $foundsRows->count());
        }

        $foundsRows = $dataTable->findRows([self::STRING_COLUMN => 'Value' . $nTimes]);
        $this->assertEquals($nEntries, $foundsRows->count());
        
        // Time info should be irrelevant for the search:
        $foundsRows3 = $dataTable->findRows(['valid_from'=> $timeZero,
            self::STRING_COLUMN => 'Value3']);
        $this->assertEquals($nEntries, $foundsRows3->count());
        
        $foundsRows3 = $dataTable->findRows(['valid_until'=> $timeZero,
            self::STRING_COLUMN => 'Value3']);
        $this->assertEquals($nEntries, $foundsRows3->count());
        
        $foundsRows3 = $dataTable->findRows(['valid_from'=> $timeZero,
            'valid_until' => $timeZero,
            self::STRING_COLUMN => 'Value3']);
        $this->assertEquals($nEntries, $foundsRows3->count());

        // Search the keys in the times they are valid
        $foundRows4 = $dataTable->findRowsWithTime(
            [self::STRING_COLUMN => 'Value3'],
            false,
            '2016-01-01 12:00:00'
        );
        $this->assertEquals(10, $foundRows4->count());
        
        // timestamps should be fine as well
        $foundRows4b = $dataTable->findRowsWithTime(
            [self::STRING_COLUMN => 'Value3'],
            false,
            // a day ago
            TimeString::fromVariable(time()-86400)
        );
        $this->assertEquals(10, $foundRows4b->count());
        
        $foundRows5 = $dataTable->findRowsWithTime(
            [self::STRING_COLUMN => 'Value2'],
            false,
            '2015-01-01 12:00:00'
        );
        $this->assertEquals(10, $foundRows5->count());
        
        $foundRows6 = $dataTable->findRowsWithTime([self::STRING_COLUMN => 'Value1'],
            false,
            '2014-01-01 12:00:00'
        );
        $this->assertEquals(10, $foundRows6->count());
        
        // Search the common key, only the latest version should
        // be returned
        $foundRows7 = $dataTable->findRows([self::INT_COLUMN => $someInt]);
        $this->assertEquals(10, $foundRows7->count());
        foreach ($foundRows7 as $row) {
            $this->assertEquals('Value3', $row[self::STRING_COLUMN]);
        }
        
        // Search the common key at other times
        $foundRows8 = $dataTable->findRowsWithTime(
            [self::INT_COLUMN => $someInt],
            false,
            '2015-01-01 12:00:00'
        );
        $this->assertEquals(10, $foundRows8->count());
        foreach ($foundRows8 as $row) {
            $this->assertEquals('Value2', $row[self::STRING_COLUMN]);
        }
        
        $foundRows9 = $dataTable->findRowsWithTime(
            [self::INT_COLUMN => $someInt],
            false,
            '2014-01-01 12:00:00'
        );
        $this->assertEquals(10, $foundRows9->count());
        foreach ($foundRows9 as $row) {
            $this->assertEquals('Value1', $row[self::STRING_COLUMN]);
        }
        
        $foundRows10 = $dataTable->findRowsWithTime(
            [self::INT_COLUMN => $someInt],
            false,
            '2013-01-01'
        );
        $this->assertEquals(10, $foundRows10->count());
        foreach ($foundRows10 as $row) {
            $this->assertTrue(is_null($row[self::STRING_COLUMN]));
        }
        
        $foundRows11 = $dataTable->findRowsWithTime(
            [self::INT_COLUMN => $someInt],
            false,
            '2000-01-01 12:00:00'
        );
        $this->assertEquals(0, $foundRows11->count());
    }

    /**
     * @throws RowAlreadyExists
     * @throws InvalidTimeStringException
     */
    #[Test]
    public function testCreateRowWithTime(): void
    {
        /** @var PdoUnitemporalDataTable $dataTable */
        $dataTable = $this->getTestDataTable();
        $time = TimeString::now();


        // Bad time
        $exceptionCaught = false;
        try{
            $dataTable->createRowWithTime(
                [$this->getIdColumnName() => 1, self::STRING_COLUMN_2 => 'test'],
                'BadTime');
        } catch (InvalidTimeStringException|RowAlreadyExists) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(UnitemporalDataTable::ERROR_INVALID_TIME, $dataTable->getErrorCode());

        $id1 = $dataTable->createRowWithTime(
            [$this->getIdColumnName() => 1, self::STRING_COLUMN_2 => 'test'],
            $time
        );
        $this->assertEquals(1, $id1);

        // ID is not an integer: a new id must be generated

        $id2 = $dataTable->createRowWithTime([$this->getIdColumnName() => 'NotaNumber',self::STRING_COLUMN_2 => 'test2'],$time);
        $this->assertNotEquals($id1, $id2);

        // Trying to create an existing row
        $exceptionCaught = false;
        try {
            $dataTable->createRowWithTime([$this->getIdColumnName() => 1,
                self::STRING_COLUMN_2 => 'AnotherValue'], $time);
        } catch(RowAlreadyExists) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $row = $dataTable->getRow($id1);
        $this->assertNotNull($row);
        $this->assertEquals('test', $row[self::STRING_COLUMN_2]);
    }

    /**
     * @throws RowAlreadyExists
     * @throws InvalidTimeStringException
     */
    #[Test]
    public function testDeleteRowWithTime(): void
    {
        /** @var PdoUnitemporalDataTable $dataTable */
        $dataTable = $this->getTestDataTable();
        
        $newId = $dataTable->createRow([self::STRING_COLUMN_2 => 'test']);
        $this->assertNotFalse($newId);

        // Bad time
        $exceptionCaught = false;
        try{
            $dataTable->deleteRowWithTime($newId, 'BadTime');
        } catch (InvalidTimeStringException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(UnitemporalDataTable::ERROR_INVALID_TIME, $dataTable->getErrorCode());


        $time = TimeString::now();
        
        $result = $dataTable->deleteRowWithTime($newId, $time);
        $this->assertEquals($newId, $result);

    }

    /**
     * @throws InvalidTimeStringException
     */
    #[Test]
    public function testGetAllRowsWithTime(): void
    {
        /**
         * @var PdoUnitemporalDataTable $dataTable
         */
        $dataTable = $this->getTestDataTable();

        $this->assertEquals(0, iterator_count($dataTable->getAllRowsWithTime('2019-01-01')));
    }

    /**
     * @throws RowAlreadyExists
     * @throws InvalidTimeStringException
     * @throws InvalidRowUpdateTime
     * @throws RowDoesNotExist
     */
    #[Test]
    public function testBadTimes(): void
    {

        /**
         * @var PdoUnitemporalDataTable $dataTable
         */
        $dataTable = $this->getTestDataTable();

        // get all rows
        $exceptionCaught = false;
        try {
            $dataTable->getAllRowsWithTime('BadTime');
        } catch (InvalidTimeStringException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(UnitemporalDataTable::ERROR_INVALID_TIME, $dataTable->getErrorCode());

        $newId = $dataTable->createRowWithTime([self::INT_COLUMN => 1000], '2010-10-10 10:10:10');

        $this->assertNotEquals(0, $newId);

        // Get row
        $exceptionCaught = false;
        $theRow = [];
        try {
            $theRow = $dataTable->getRowWithTime($newId, 'BadTime');
        } catch (InvalidTimeStringException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(UnitemporalDataTable::ERROR_INVALID_TIME, $dataTable->getErrorCode());
        $this->assertEquals([], $theRow);

        // update row
        $exceptionCaught = false;
        try {
            $dataTable->realUpdateRowWithTime([ $this->getIdColumnName() => $newId, self::INT_COLUMN => 1001], 'BadTime');
        } catch (InvalidTimeStringException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(UnitemporalDataTable::ERROR_INVALID_TIME, $dataTable->getErrorCode());

        $theRow = $dataTable->getRow($newId);
        $this->assertNotNull($theRow);
        $this->assertEquals(1000, $theRow[self::INT_COLUMN]);


        // find Rows
        $foundRows = [];
        $exceptionCaught = false;
        try {
            $foundRows = $dataTable->findRowsWithTime([ self::INT_COLUMN => 1000], 0, 'BadTime');
        } catch (InvalidTimeStringException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(UnitemporalDataTable::ERROR_INVALID_TIME, $dataTable->getErrorCode());

        $this->assertEquals([], $foundRows);

    }


    /**
     * @throws RowAlreadyExists
     * @throws InvalidTimeStringException
     * @throws MalformedStringException
     * @throws InvalidTimeZoneException
     */
    #[Test]
    public function testRowExists(): void
    {
        /**
         * @var PdoUnitemporalDataTable $dataTable
         */
        $dataTable = $this->getTestDataTable();

        $rowId = $dataTable->createRowWithTime([self::INT_COLUMN => 1000], TimeString::now());

        $this->assertTrue($dataTable->rowExistsWithTime($rowId,TimeString::now()));
        $this->assertFalse($dataTable->rowExistsWithTime($rowId + 1,TimeString::now()));



        $this->assertFalse($dataTable->rowExistsWithTime($rowId, TimeString::fromString('2010-10-10')));

    }

    /**
     * @throws InvalidSearchType
     * @throws InvalidSearchSpec
     */
    #[Test]
    public function testSearchWithTime(): void
    {
        /**
         * @var PdoUnitemporalDataTable $dataTable
         */
        $dataTable = $this->getTestDataTable();
        // search is not implemented yet
        $this->assertEquals(0, $dataTable->searchWithTime([], DataTable::SEARCH_AND, TimeString::now())->count());
        $this->assertEquals(DataTable::ERROR_NOT_IMPLEMENTED, $dataTable->getErrorCode());
    }


    /**
     * @throws InvalidTimeStringException
     * @throws InvalidTimeZoneException
     * @throws RowDoesNotExist
     * @throws InvalidRowForUpdate
     * @throws RowAlreadyExists
     * @throws InvalidRowUpdateTime
     */
    #[Test]
    public function testUpdateRowWithTime(): void
    {
        /**
         * @var PdoUnitemporalDataTable $dataTable
         */
        $dataTable = $this->getTestDataTable();

        $rowId = $dataTable->createRowWithTime([self::INT_COLUMN => 1000], TimeString::now());

        $theRow = $dataTable->getRow($rowId);
        $this->assertNotNull($theRow);

        $theRow[self::INT_COLUMN] = 1001;

        $dataTable->updateRowWithTime($theRow, TimeString::now());
        $theRow2 = $dataTable->getRow($rowId);
        $this->assertNotNull($theRow2);
        $this->assertEquals($theRow[self::INT_COLUMN], $theRow2[self::INT_COLUMN]);

        $exceptionCaught = false;
        try {
            $dataTable->updateRowWithTime([self::INT_COLUMN => 1002], TimeString::now());
        } catch (InvalidRowForUpdate){
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(DataTable::ERROR_ID_NOT_SET, $dataTable->getErrorCode());

        // Update with time before last update
        $theRow[self::INT_COLUMN] = 1002;
        $exceptionCaught = false;
        try {
            $dataTable->updateRowWithTime($theRow, TimeString::fromTimeStamp(time() - 600));
        } catch (InvalidRowUpdateTime){
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(UnitemporalDataTable::ERROR_INVALID_ROW_UPDATE_TIME, $dataTable->getErrorCode());

    }

    /**
     * @throws InvalidTimeStringException
     * @throws InvalidTimeZoneException
     * @throws MalformedStringException
     * @throws RowDoesNotExist
     * @throws InvalidRowForUpdate
     * @throws RowAlreadyExists
     * @throws InvalidRowUpdateTime
     */
    #[Test]
    public function testRowHistory(): void
    {
        /**
         * @var PdoUnitemporalDataTable $dataTable
         */
        $dataTable = $this->getTestDataTable();


        $times = [
            '2010-01-01',
            '2014-01-01',
            '2015-01-01',
            '2016-01-01'];

        $initialIntValue = 1000;
        $rowId = $dataTable->createRowWithTime([ self::INT_COLUMN => 1000], TimeString::fromString($times[0]));
        for($i = 1; $i < count($times); $i++){
            $dataTable->updateRowWithTime(
                [ $this->getIdColumnName() => $rowId, self::INT_COLUMN => $initialIntValue+$i ],
                TimeString::fromString($times[$i]));
        }

        $rowHistory = $dataTable->getRowHistory($rowId);
        $this->assertCount(4, $rowHistory);
        for($i=0; $i<count($rowHistory); $i++) {
            $this->assertEquals($rowId, $rowHistory[$i][$this->getIdColumnName()]);
            $this->assertEquals($initialIntValue+$i, $rowHistory[$i][self::INT_COLUMN]);
            $this->assertEquals(TimeString::fromString($times[$i]),$rowHistory[$i][PdoUnitemporalDataTable::FIELD_VALID_FROM]);
        }
        $this->assertEquals(TimeString::END_OF_TIMES,$rowHistory[count($rowHistory)-1][PdoUnitemporalDataTable::FIELD_VALID_UNTIL]);

        $exceptionCaught = false;
        try {
            $dataTable->getRowHistory($rowId + 5);
        } catch (InvalidArgumentException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(DataTable::ERROR_ROW_DOES_NOT_EXIST, $dataTable->getErrorCode());

    }

    /**
     * @throws InvalidArgumentException
     * @throws RowDoesNotExist
     * @throws InvalidRowForUpdate
     */
    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function testConsistency(): void
    {
        $dataTable = $this->getMockBuilder(PdoUnitemporalDataTable::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUniqueIdsWithTime', 'getRowHistory'])
            ->getMock();

        $dataTable->expects($this->any())->method('getUniqueIdsWithTime')->willReturn(new ArrayIterator([1]));

        // 1. Valid history
        $dataTable->expects($this->any())->method('getRowHistory')
            ->willReturnOnConsecutiveCalls(
                [
                    [
                        PdoUnitemporalDataTable::FIELD_VALID_FROM => '2020-01-01 00:00:00.000000',
                        PdoUnitemporalDataTable::FIELD_VALID_UNTIL => '2020-02-01 00:00:00.000000'
                    ],
                    [
                        PdoUnitemporalDataTable::FIELD_VALID_FROM => '2020-02-01 00:00:00.000000',
                        PdoUnitemporalDataTable::FIELD_VALID_UNTIL => TimeString::END_OF_TIMES
                    ]
                ],
                // 2. Invalid range (until < from)
                [
                    [
                        PdoUnitemporalDataTable::FIELD_VALID_FROM => '2020-02-01 00:00:00.000000',
                        PdoUnitemporalDataTable::FIELD_VALID_UNTIL => '2020-01-01 00:00:00.000000'
                    ]
                ],
                // 3. Zero range (until == from)
                [
                    [
                        PdoUnitemporalDataTable::FIELD_VALID_FROM => '2020-01-01 00:00:00.000000',
                        PdoUnitemporalDataTable::FIELD_VALID_UNTIL => '2020-01-01 00:00:00.000000'
                    ]
                ],
                // 4. Overlap
                [
                    [
                        PdoUnitemporalDataTable::FIELD_VALID_FROM => '2020-01-01 00:00:00.000000',
                        PdoUnitemporalDataTable::FIELD_VALID_UNTIL => '2020-02-01 00:00:00.000000'
                    ],
                    [
                        PdoUnitemporalDataTable::FIELD_VALID_FROM => '2020-01-15 00:00:00.000000',
                        PdoUnitemporalDataTable::FIELD_VALID_UNTIL => TimeString::END_OF_TIMES
                    ]
                ],
                // 5. Gap
                [
                    [
                        PdoUnitemporalDataTable::FIELD_VALID_FROM => '2020-01-01 00:00:00.000000',
                        PdoUnitemporalDataTable::FIELD_VALID_UNTIL => '2020-02-01 00:00:00.000000'
                    ],
                    [
                        PdoUnitemporalDataTable::FIELD_VALID_FROM => '2020-03-01 00:00:00.000000',
                        PdoUnitemporalDataTable::FIELD_VALID_UNTIL => TimeString::END_OF_TIMES
                    ]
                ]
            );

        // 1. Valid
        $issues = $dataTable->checkConsistency([1]);
        $this->assertCount(0, $issues);

        // 2. Invalid range
        $issues = $dataTable->checkConsistency([1]);
        $this->assertCount(1, $issues);
        $this->assertEquals(PdoUnitemporalDataTable::REPORT_ERROR_INVALID_TIME_RANGE, $issues[0]['code']);

        // 3. Zero range
        $issues = $dataTable->checkConsistency([1]);
        $this->assertCount(1, $issues);
        $this->assertEquals(PdoUnitemporalDataTable::REPORT_WARNING_ZERO_TIME_RANGE, $issues[0]['code']);

        // 4. Overlap
        $issues = $dataTable->checkConsistency([1]);
        $this->assertCount(1, $issues);
        $this->assertEquals(PdoUnitemporalDataTable::REPORT_ERROR_OVERLAPPING_VERSIONS, $issues[0]['code']);

        // 5. Gap
        $issues = $dataTable->checkConsistency([1]);
        $this->assertCount(1, $issues);
        $this->assertEquals(PdoUnitemporalDataTable::REPORT_INFO_GAP, $issues[0]['code']);
    }

    /**
     * @throws RowAlreadyExists
     * @throws InvalidSearchType
     * @throws InvalidSearchSpec
     */
    #[Test]
    public function testSearchAndFindWithMaxResults(): void
    {
        $dataTable = $this->getTestDataTable();
        $dataTable->createRow([self::INT_COLUMN => 10, self::STRING_COLUMN => 'test']);
        $dataTable->createRow([self::INT_COLUMN => 20, self::STRING_COLUMN => 'test']);
        $dataTable->createRow([self::INT_COLUMN => 30, self::STRING_COLUMN => 'test']);

        $spec = [
            ['column' => self::STRING_COLUMN, 'condition' => DataTable::COND_EQUAL_TO, 'value' => 'test']
        ];

        $results = $dataTable->search($spec, DataTable::SEARCH_AND, 2);
        $this->assertEquals(2, $results->count());

        $results = $dataTable->findRows([self::STRING_COLUMN => 'test'], 1);
        $this->assertEquals(1, $results->count());
    }
}
