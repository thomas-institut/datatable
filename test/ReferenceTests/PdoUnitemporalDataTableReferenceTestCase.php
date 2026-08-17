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
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\DataTable\Exception\InvalidRowUpdateTime;
use ThomasInstitut\DataTable\Exception\InvalidTimeStringException;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\Exception\RowDoesNotExist;
use ThomasInstitut\DataTable\PdoProvider\PdoProvider;
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

}
