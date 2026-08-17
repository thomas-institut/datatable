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
use PHPUnit\Framework\MockObject\Exception;
use RuntimeException;
use ThomasInstitut\DataTable\DataTable;
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\DataTable\Exception\InvalidSearchSpec;
use ThomasInstitut\DataTable\Exception\InvalidSearchType;
use ThomasInstitut\DataTable\Exception\InvalidTimeStringException;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\PdoDataTable;
use ThomasInstitut\DataTable\PdoProvider\PdoProvider;
use ThomasInstitut\DataTable\PdoProvider\SimplePdoProvider;
use ThomasInstitut\DataTable\PdoUnitemporalDataTable;
use ThomasInstitut\DataTable\UnitemporalDataTable;
use ThomasInstitut\TimeString\TimeString;


/**
 * Reference test cases for PdoUnitemporalDataTable implementations.
 *
 * Extends UnitemporalDataTableReferenceTestCase with PDO-specific tests.
 * Subclasses must provide dialect-specific setup
 * (DB creation, DDL, PDO connections) via abstract methods.
 */
abstract class PdoUnitemporalDataTableReferenceTestCase extends UnitemporalDataTableReferenceTestCase
{


    /**
     * Construct a PdoUnitemporalDataTable for the standard test table.
     */
    abstract protected function constructPdoUnitemporalDataTable(PDO $pdo): PdoUnitemporalDataTable;

    /**
     * Construct a PdoUnitemporalDataTable for an arbitrary table name.
     */
    abstract protected function constructPdoUnitemporalDataTableForTable(PDO|PdoProvider $pdoOrProvider, string $tableName): PdoUnitemporalDataTable;

    protected function constructPdoDataTable(PDO $pdo): PdoUnitemporalDataTable
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

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function testTransactionFailures(): void
    {
        $intResp = $this->getMockColumnInfoResponse();
        $dtResp = $this->getMockDatetimeColumnInfoResponse();

        // Helper to create a fresh mock PDO + provider for each subtest
        /**
         * @throws Exception
         */
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

    /**
     * @throws RowAlreadyExists
     */
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
     * Checks invalid-time handling for operations that exist only on the PDO
     * implementation or expose its internal update method.
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

        // The raw PDO update operation is not part of UnitemporalDataTable.
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

    }





    /**
     * @throws InvalidArgumentException
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
     * @throws InvalidSearchSpec
     * @throws InvalidSearchType
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
