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

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;


require '../vendor/autoload.php';



/**
 * Reference test cases for DataTable implementations
 *
 * Every implementation test should extend this class and provide an implementation
 * of the getTestDataTable and supportDataAccessSessions methods.
 *
 * There should be one single underlying data storage to be shared among potentially many
 * DataTable instances. For example, a single table in a common MySql database or a
 * single array for InMemoryDataTables.
 *
 *
 * @author Rafael Nájera <rafael@najera.ca>
 */
abstract class DataTableTestCase extends TestCase
{

    const INT_COLUMN = 'the_int';
    const STRING_COLUMN = 'a_string';
    const STRING_COLUMN_2 = 'another_string';
    
    public int $numRows = 10;
    public int $numIterations = 5;


    /**
     * Returns true if there can be multiple data access sessions (e.g. PDO connections) available
     * in the test scenario.
     *
     * @return bool
     */
    abstract public function multipleDataAccessSessionsAvailable() : bool;

    /**
     * Returns a DataTable that manipulates the single underlying storage.
     *
     * If $resetTable is true, the underlying storage will be reset and emptied.
     *
     * If $newSession is true and the test scenario supports multiple sessions, the
     * returned DataTable should use a new session. If false, a single common session
     * must be used.
     *
     * @param bool $resetTable
     * @param bool $newSession
     * @return DataTable
     */
    abstract public function getTestDataTable(bool $resetTable = true, bool $newSession = false) : DataTable;



    private function fillUpTestDataTable(DataTable $dataTable) : DataTable
    {
        for ($i = 1; $i <= $this->numRows; $i++) {
            $someRow = [ self::INT_COLUMN => $i, self::STRING_COLUMN => "textvalue$i"];
            try {
                $dataTable->createRow($someRow);
            } catch (RowAlreadyExists) {
                // should never happen
            }
        }
        return $dataTable;
    }


    /**
     * @throws RowAlreadyExists
     */
    public function testCreationAndDeletion()
    {
        
        $dataTable = $this->getTestDataTable();
        $idColumn = $dataTable->getIdColumnName();
        
        $this->assertSame(false, $dataTable->rowExists(1));
        
        $ids = [];
        for ($i = 1; $i <= $this->numRows; $i++) {
            $someRow = [  self::INT_COLUMN => $i, self::STRING_COLUMN => "textvalue$i"];
            $testMsg = "Creating rows, iteration $i";
            $newId = $dataTable->createRow($someRow);
            $this->assertTrue($dataTable->rowExists($newId), $testMsg);
            $ids[] = $newId;
        }

        sort($ids, SORT_NUMERIC);

        $this->assertEquals($ids, $dataTable->getUniqueIds());

        // Some random deletions and additions
        for ($i = 0; $i < $this->numIterations; $i++) {
            $theId = $ids[rand(0, $this->numRows-1)];
            $testMsg = "Random deletions and additions,  iteration $i, "
                    . "$idColumn=$theId";
            $this->assertTrue($dataTable->rowExists($theId), $testMsg);
            $this->assertEquals(1, $dataTable->deleteRow($theId), $testMsg);
            $this->assertFalse($dataTable->rowExists($theId), $testMsg);
            $this->assertFalse(in_array($theId, $dataTable->getUniqueIds()), $testMsg);
            $newId = $dataTable->createRow([ $idColumn => $theId,
                self::INT_COLUMN => $theId, self::STRING_COLUMN => "textvalue$theId" ]);
            $this->assertSame($theId, $newId, $testMsg);
            $this->assertTrue(in_array($theId, $dataTable->getUniqueIds()), $testMsg);
        }
    }

    public function testFindSingle()
    {
        $dataTable = $this->fillUpTestDataTable($this->getTestDataTable());
        $idColumn = $dataTable->getIdColumnName();

        // Random searches
        $nSearches = $this->numIterations;
        for ($i = 0; $i < $nSearches; $i++) {
            $someInt = rand(1, $this->numRows);
            $someTextvalue = "textvalue$someInt";
            $testMsg = "Random searches,  iteration $i, int=$someInt";
            $theRows = $dataTable->findRows([self::INT_COLUMN => $someInt], 1);
            $this->assertEquals(1, $theRows->count(), $testMsg);
            $this->assertTrue(is_int($theRows->getFirst()[$idColumn]), $testMsg);
            $theRows2 = $dataTable->findRows([self::STRING_COLUMN => $someTextvalue], 1);
            $this->assertEquals(1, $theRows2->count(), $testMsg);
            $this->assertEquals($theRows->getFirst()[$idColumn], $theRows2->getFirst()[$idColumn], $testMsg);
            $rowId = $dataTable->getIdForKeyValue(
                self::STRING_COLUMN,
                $someTextvalue
            );
            $this->assertNotEquals(DataTable::NULL_ROW_ID, $rowId, $testMsg);
            $this->assertEquals($theRows->getFirst()[$idColumn], $rowId);
            $theRows3 = $dataTable->findRows([self::INT_COLUMN => $someInt,
                self::STRING_COLUMN => $someTextvalue]);
            $this->assertEquals(1, $theRows3->count(), $testMsg);
            $this->assertTrue(is_int($theRows3->getFirst()[$idColumn]), $testMsg);
            $this->assertEquals($theRows->getFirst()[$idColumn], $theRows3->getFirst()[$idColumn], $testMsg);
        }
    }

    /**
     * @throws RowAlreadyExists
     */
    public function testFindMultiple()
    {
        $dataTable = $this->getTestDataTable();
        
        for ($i = 0; $i < $this->numRows; $i++) {
            $dataTable->createRow([self::INT_COLUMN => 100]);
        }
        
        for ($i = 1; $i <= $this->numRows; $i++) {
            $this->assertEquals($i, $dataTable->findRows([self::INT_COLUMN => 100], $i)->count());
        }
        
        for ($i = $this->numRows+1;
            $i <= $this->numRows+1+ $this->numIterations; $i++) {
            $this->assertEquals(
                $this->numRows,
                $dataTable->findRows([self::INT_COLUMN => 100], $i)->count()
            );
        }
    }

    private function getStringValue(string $prefix, int $value) : string {
        return  $prefix . sprintf("%03d",$value);
    }

    /**
     * @throws InvalidSearchType
     * @throws RowAlreadyExists
     */
    public function testComplexSearches() {
        $dataTable = $this->getTestDataTable();
        $stringKeyName  = self::STRING_COLUMN;
        $intKeyName = self::INT_COLUMN;
        $stringValuePrefix = 'val';

        for ($i = 1; $i <= 100; $i++) {
            $dataTable->createRow([ $stringKeyName => $this->getStringValue($stringValuePrefix, $i), $intKeyName => $i]);
        }

        $testCases = [
            [
                'title' => 'No matches (equal)',
                'expectedCount' => 0,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => DataTable::COND_EQUAL_TO, 'value' => 'x' . $stringValuePrefix]
                ]
            ],
            [
                'title' => 'No matches (greater than)',
                'expectedCount' => 0,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $intKeyName, 'condition' => DataTable::COND_GREATER_THAN,  'value' =>  100]
                ]
            ],
            [
                'title' => 'Not equal to (int)',
                'expectedCount' => 99,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $intKeyName, 'condition' => DataTable::COND_NOT_EQUAL_TO,  'value' =>  1]
                ]
            ],
            [
                'title' => 'Not equal to (string)',
                'expectedCount' => 99,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => DataTable::COND_NOT_EQUAL_TO,  'value' =>  $this->getStringValue($stringValuePrefix, 1)]
                ]
            ],
            [
                'title' => 'Greater than and less than (int)',
                'expectedCount' => 80,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $intKeyName, 'condition' => DataTable::COND_GREATER_THAN,  'value' => 10],
                    [ 'column' => $intKeyName, 'condition' => DataTable::COND_LESS_OR_EQUAL_TO,  'value' => 90],
                ]
            ],
            [
                'title' => 'Greater than and less than (string)',
                'expectedCount' => 80,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => DataTable::COND_GREATER_THAN,  'value' => $this->getStringValue($stringValuePrefix, 10) ],
                    [ 'column' => $stringKeyName, 'condition' => DataTable::COND_LESS_OR_EQUAL_TO,  'value' => $this->getStringValue($stringValuePrefix, 90)],
                ]
            ],
            [
                'title' => 'Less than or greater than (int)',
                'expectedCount' => 20,
                'searchType' => DataTable::SEARCH_OR,
                'specArray' => [
                    [ 'column' => $intKeyName, 'condition' => DataTable::COND_LESS_THAN, 'value' => 11 ],
                    [ 'column' => $intKeyName, 'condition' => DataTable::COND_GREATER_THAN,  'value' =>  90 ],
                ]
            ],
            [
                'title' => 'Less than or greater than (string)',
                'expectedCount' => 20,
                'searchType' => DataTable::SEARCH_OR,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => DataTable::COND_LESS_THAN,  'value' => $this->getStringValue($stringValuePrefix, 11) ],
                    [ 'column' => $stringKeyName, 'condition' => DataTable::COND_GREATER_THAN,  'value' => $this->getStringValue($stringValuePrefix, 90)],
                ]
            ],
            [
                'title' => 'Greater than or equal to (int)',
                'expectedCount' => 10,
                'searchType' => DataTable::SEARCH_OR,
                'specArray' => [
                    [ 'column' => $intKeyName, 'condition' => DataTable::COND_GREATER_OR_EQUAL_TO,  'value' => 91]
                ]
            ],
            [
                'title' => 'Greater than or equal to (string)',
                'expectedCount' => 10,
                'searchType' => DataTable::SEARCH_OR,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => DataTable::COND_GREATER_OR_EQUAL_TO,  'value' => $this->getStringValue($stringValuePrefix, 91)],
                ]
            ]
        ];

        foreach($testCases as $testCase) {
            $exceptionCaught = false;
            try {
                $resultingRows = $dataTable->search($testCase['specArray'], $testCase['searchType']);
                $this->assertEquals($testCase['expectedCount'], $resultingRows->count(), $testCase['title']);
            } catch(InvalidSearchSpec) {
                $exceptionCaught = true;
            }
            $this->assertFalse($exceptionCaught, $testCase['title']);
        }
    }

    public function testBadlyFormedSearches() {
        $dataTable = $this->getTestDataTable();
        $stringKeyName  = self::STRING_COLUMN;

        $testCases = [
            [
                'title' => 'Empty Spec Array',
                'expectedErrorCode' => DataTable::ERROR_INVALID_SPEC_ARRAY,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => []
            ],
            [
                'title' => 'No Column',
                'expectedErrorCode' => DataTable::ERROR_INVALID_SPEC_ARRAY,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'condition' => DataTable::COND_EQUAL_TO, 'value' => 'anyValue']
                ]
            ],
            [
                'title' => 'Wrong Column (int)',
                'expectedErrorCode' => DataTable::ERROR_INVALID_SPEC_ARRAY,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => 32, 'condition' => DataTable::COND_EQUAL_TO, 'value' => 'anyValue']
                ]
            ],
            [
                'title' => 'Bad Search Type',
                'expectedErrorCode' => DataTable::ERROR_INVALID_SEARCH_TYPE,
                'searchType' => 100,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => DataTable::COND_EQUAL_TO, 'value' => 'anyValue']
                ]
            ],
            [
                'title' => 'Bad condition (wrong int)',
                'expectedErrorCode' => DataTable::ERROR_INVALID_SPEC_ARRAY,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => DataTable::COND_EQUAL_TO + 1000, 'value' => 'anyValue']
                ]
            ],
            [
                'title' => 'Bad condition (string)',
                'expectedErrorCode' => DataTable::ERROR_INVALID_SPEC_ARRAY,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => '1', 'value' => 'anyValue']
                ]
            ],
            [
                'title' => 'No value',
                'expectedErrorCode' => DataTable::ERROR_INVALID_SPEC_ARRAY,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => '1']
                ]
            ]
        ];

        foreach($testCases as $testCase) {
            try {
                $dataTable->search($testCase['specArray'], $testCase['searchType']);
            } catch(InvalidArgumentException) {

            }

            $this->assertEquals($testCase['expectedErrorCode'], $dataTable->getErrorCode(), $testCase['title']);
        }

    }

    /**
     * @throws RowDoesNotExist
     * @throws InvalidArgumentException
     * @throws InvalidRowForUpdate
     */
    public function testUpdate()
    {
        $dataTable = $this->fillUpTestDataTable($this->getTestDataTable());
        $idColumn = $dataTable->getIdColumnName();

        $nUpdates = $this->numIterations;
        for ($i = 0; $i < $nUpdates; $i++) {
            $someInt = rand(1, $this->numRows);
            $newTextValue = "NewTextValue$someInt";
            $testMsg = "Random updates,  iteration $i, int=$someInt, new value: $newTextValue";
            $theRows = $dataTable->findRows([self::INT_COLUMN => $someInt], 1);
            $this->assertEquals(1, $theRows->count(), $testMsg);
            $theRow = $theRows->getFirst();
            $theId = $theRow[$idColumn];
            $dataTable->updateRow([$idColumn=>$theId,  self::STRING_COLUMN => $newTextValue]);
            $theRow2 = $dataTable->getRow($theId);
            $this->assertEquals(
                $newTextValue,
                $theRow2[self::STRING_COLUMN],
                $testMsg
            );
            $this->assertEquals($someInt, $theRow2[self::INT_COLUMN], $testMsg);
        }
    }


     public function testNonExistentRows()
    {
        $dataTable = $this->getTestDataTable();
        $this->assertFalse($dataTable->rowExists(1));

        try {
            $dataTable->getRow(1);
        } catch (InvalidArgumentException) {}

        $this->assertEquals(DataTable::ERROR_ROW_DOES_NOT_EXIST, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());

        $this->assertEquals(0, $dataTable->findRows(['key' => 'someValue'], 1)->count());

        $this->assertEquals(DataTable::NULL_ROW_ID, $dataTable->getIdForKeyValue('key', 'someValue'));
        $this->assertEquals(0, $dataTable->getAllRows()->count());
        $this->assertEquals(0, $dataTable->deleteRow(1));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testCreateRow()
    {
        $dataTable = $this->getTestDataTable();
        $idColumn = $dataTable->getIdColumnName();

        $res = $dataTable->createRow([$idColumn => 1, self::STRING_COLUMN_2 => 'test']);
        $this->assertEquals(1, $res);

        // Trying to create an existing row
        $exceptionCaught = false;
        try{
            $dataTable->createRow([$idColumn => 1, self::STRING_COLUMN_2 => 'anotherValue']);
        }
        catch (InvalidArgumentException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(DataTable::ERROR_ROW_ALREADY_EXISTS, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());
        $row = $dataTable->getRow(1);
        $this->assertEquals('test', $row[self::STRING_COLUMN_2]);

        // invalid ID: a new one must be generated
        $newId = $dataTable->createRow([$idColumn => 'notaNumber', self::STRING_COLUMN_2 => 'test']);
        $this->assertNotEquals(1, $newId);

        // no ID: a new one must be generated
        $newId2 = $dataTable->createRow([self::STRING_COLUMN_2 => 'test2']);
        $this->assertNotEquals(1, $newId2);
        $this->assertNotEquals($newId, $newId2);

    }

    /**
     * @throws RowAlreadyExists
     * @throws RowDoesNotExist
     */
    public function testUpdateRow()
    {
        $dataTable = $this->getTestDataTable();
        $idColumn = $dataTable->getIdColumnName();
        $theRow = [
            $idColumn => 1,
            self::STRING_COLUMN_2 => 'test',
            self::INT_COLUMN => 0,
            self::STRING_COLUMN => 0
        ];
        $res = $dataTable->createRow($theRow);
        $this->assertEquals(1, $res);
        
        // No id in row
        $exceptionCaught = false;
        try {
            $dataTable->updateRow([]);
        } catch (InvalidArgumentException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(DataTable::ERROR_ID_NOT_SET, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());


        // no id in row
        $exceptionCaught = false;
        try {
            $dataTable->updateRow([self::STRING_COLUMN_2 => 'testUpdate']);
        } catch (InvalidArgumentException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(DataTable::ERROR_ID_NOT_SET, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());

        // Check that not updates were made!
        $updatedRow = $dataTable->getRow(1);
        $this->assertEquals($theRow, $updatedRow);
        
        // id 0, which is invalid
        $exceptionCaught = false;
        try {
            $dataTable->updateRow([$idColumn=> 0, self::STRING_COLUMN_2 => 'testUpdate']);
        } catch (InvalidArgumentException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(DataTable::ERROR_ID_IS_ZERO, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());

        // Check that no updates were made!
        $updatedRow = $dataTable->getRow(1);
        $this->assertEquals($theRow, $updatedRow);

        // Id not integer
        $exceptionCaught = false;
        try {
            $dataTable->updateRow([$idColumn=> '1', self::STRING_COLUMN_2 => 'testUpdate']);
        } catch (InvalidArgumentException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(DataTable::ERROR_ID_NOT_INTEGER, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());

        // Check that no updates were made!
        $updatedRow = $dataTable->getRow(1);
        $this->assertEquals($theRow, $updatedRow);

        // Row does not exist
        $exceptionCaught = false;
        try {
            $dataTable->updateRow([$idColumn=> 2, self::STRING_COLUMN_2 => 'testUpdate']);
        } catch (InvalidArgumentException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(DataTable::ERROR_ROW_DOES_NOT_EXIST, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());

        // Check that no updates were made!
        $updatedRow = $dataTable->getRow(1);
        $this->assertEquals($theRow, $updatedRow);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testRowExistsById()
    {
        $dataTable = $this->getTestDataTable();
        $idColumn = $dataTable->getIdColumnName();
        $theRow = [$idColumn => 1, self::STRING_COLUMN_2 => 'test'];
        
        $res = $dataTable->createRow($theRow);
        $this->assertEquals(1, $res);
        
        $this->assertTrue($dataTable->rowExists(1));
        $this->assertFalse($dataTable->rowExists(0));
    }

    public function testArrayAccess() {
        $dataTable = $this->getTestDataTable();
        $idColumn = $dataTable->getIdColumnName();


        $dataTable[] = [ $idColumn => 1, self::STRING_COLUMN_2 => 'one'];
        $dataTable[] = [ $idColumn => 2, self::STRING_COLUMN_2 => 'two'];
        $dataTable[25]  = [self::STRING_COLUMN_2 => 25];

        $this->assertEquals('one', $dataTable[1][self::STRING_COLUMN_2]);
        $this->assertEquals('two', $dataTable[2][self::STRING_COLUMN_2]);
        $this->assertEquals(25, $dataTable[25][self::STRING_COLUMN_2]);
        $this->assertFalse(isset($dataTable[20]));
        unset($dataTable[25]);
        $this->assertFalse($dataTable->rowExists(25));
    }


    /**
     * @throws RowAlreadyExists
     * @throws RowDoesNotExist
     * @throws InvalidRowForUpdate
     */
    public function testEscaping()
    {
        $dataTable = $this->getTestDataTable();
        $idColumn = $dataTable->getIdColumnName();

        $rowId = $dataTable->createRow([self::INT_COLUMN => 120]);
        $theRow = $dataTable->getRow($rowId);
        $this->assertSame($rowId, $theRow[$idColumn]);
        $this->assertEquals(120, $theRow[self::INT_COLUMN]);
        $this->assertFalse(isset($theRow[self::STRING_COLUMN]));
        $dataTable->updateRow([$idColumn => $rowId, self::INT_COLUMN => null,
            self::STRING_COLUMN => 'Some string']);
        $theRow2 = $dataTable->getRow($rowId);
        $this->assertTrue(is_null($theRow2[self::INT_COLUMN]));
        $this->assertEquals('Some string', $theRow2[self::STRING_COLUMN]);

    }

    public function testSameDataAccess() {

        $dataTable = $this->getTestDataTable();
        $dataTableTwo = $this->getTestDataTable(false);
        $idColumn = $dataTable->getIdColumnName();

        $dataTable[] = [ $idColumn => 1, self::STRING_COLUMN_2 => 'one'];
        $dataTable[] = [ $idColumn => 2, self::STRING_COLUMN_2 => 'two'];

        $this->assertTrue($dataTable->rowExists(1));
        $this->assertTrue($dataTable->rowExists(2));
        $this->assertTrue($dataTableTwo->rowExists(1));
        $this->assertTrue($dataTableTwo->rowExists(2));

        $allRows = $dataTable->getAllRows();
        $allRowsTwo = $dataTableTwo->getAllRows();
        $this->assertEquals(2, $allRows->count());
        $this->assertEquals(2, $allRowsTwo->count());

        unset($dataTableTwo[2]);

        $this->assertTrue($dataTable->rowExists(1));
        $this->assertFalse($dataTable->rowExists(2));
        $this->assertTrue($dataTableTwo->rowExists(1));
        $this->assertFalse($dataTableTwo->rowExists(2));

        $allRows = $dataTable->getAllRows();
        $allRowsTwo = $dataTableTwo->getAllRows();
        $this->assertEquals(1, $allRows->count());
        $this->assertEquals(1, $allRowsTwo->count());
    }

    public function testTransactions() {

        $dataTable = $this->getTestDataTable();
        $dataTable[20] = [ self::STRING_COLUMN => '20' ];
        $dataTable[21] = [ self::STRING_COLUMN => '21' ];
        $dataTable[22] = [ self::STRING_COLUMN => '22' ];

        $idColumn = $dataTable->getIdColumnName();
        if ($dataTable->supportsTransactions()) {
            $dtSameSession = $this->getTestDataTable(false, false);
            $dtOtherSession = $this->multipleDataAccessSessionsAvailable() ? $this->getTestDataTable(false, true) : null;

            $dataTables = [ $dataTable, $dtSameSession, $dtOtherSession];

            $this->assertTrue($dataTable->startTransaction());
            // only the DataTable where the transaction started must be in transaction
            $this->assertTrue($dataTable->isInTransaction());
            $this->assertFalse($dtSameSession->isInTransaction());
            $dtOtherSession && $this->assertFalse($dtOtherSession->isInTransaction());

            // only the two DataTables sharing the session should have the database in transaction
            $this->assertTrue($dataTable->isUnderlyingDatabaseInTransaction());
            $this->assertTrue($dtSameSession->isUnderlyingDatabaseInTransaction());
            $dtOtherSession && $this->assertFalse($dtOtherSession->isUnderlyingDatabaseInTransaction());

            // 1. Do changes and commit

            $dataTable[] = [ $idColumn => 1, self::STRING_COLUMN_2 => '1'];
            $dataTable[] = [ $idColumn => 2, self::STRING_COLUMN_2 => '2'];
            $dataTable[20] = [ self::STRING_COLUMN => 'newValue'];
            unset($dataTable[22]);

            $this->assertTrue($dataTable->rowExists(1));
            $this->assertTrue($dataTable->rowExists(2));
            $this->assertEquals('newValue', $dataTable[20][self::STRING_COLUMN]);
            $this->assertFalse($dataTable->rowExists(22));

            $this->assertTrue($dtSameSession->rowExists(1));
            $this->assertTrue($dtSameSession->rowExists(2));
            $this->assertEquals('newValue', $dtSameSession[20][self::STRING_COLUMN]);
            $this->assertFalse($dtSameSession->rowExists(22));


            $dtOtherSession && $this->assertFalse($dtOtherSession->rowExists(1));
            $dtOtherSession && $this->assertFalse($dtOtherSession->rowExists(2));
            $dtOtherSession && $this->assertNotEquals('newValue', $dtOtherSession[20][self::STRING_COLUMN]);
            $dtOtherSession && $this->assertTrue($dtOtherSession->rowExists(22));

            $this->assertTrue($dataTable->commit());

            foreach( $dataTables as $dt) {
                $dt && $this->assertFalse($dt->isInTransaction());
                $dt && $this->assertFalse($dt->isUnderlyingDatabaseInTransaction());
                $dt && $this->assertTrue($dt->rowExists(1));
                $dt && $this->assertTrue($dt->rowExists(2));
                $dt && $this->assertEquals('newValue', $dt[20][self::STRING_COLUMN]);
                $dt && $this->assertFalse($dt->rowExists(22));
            }

            // 2. Change some data and roll back

            $this->assertTrue($dataTable->startTransaction());

            $dataTable[] = [ $idColumn => 3, self::STRING_COLUMN_2 => '3'];
            $dataTable[] = [ $idColumn => 4, self::STRING_COLUMN_2 => '4'];
            $dataTable[20] = [ self::STRING_COLUMN => 'evenNewerValue'];
            unset($dataTable[21]);


            $this->assertTrue($dataTable->rowExists(3));
            $this->assertTrue($dataTable->rowExists(4));
            $this->assertEquals('evenNewerValue', $dataTable[20][self::STRING_COLUMN]);
            $this->assertFalse($dataTable->rowExists(21));

            $this->assertTrue($dtSameSession->rowExists(3));
            $this->assertTrue($dtSameSession->rowExists(4));
            $this->assertEquals('evenNewerValue', $dtSameSession[20][self::STRING_COLUMN]);
            $this->assertFalse($dtSameSession->rowExists(21));

            $this->assertTrue($dataTable->rowExists(3));
            $this->assertTrue($dataTable->rowExists(4));
            $this->assertTrue($dtSameSession->rowExists(3));
            $this->assertTrue($dtSameSession->rowExists(4));

            $dtOtherSession && $this->assertFalse($dtOtherSession->rowExists(3));
            $dtOtherSession && $this->assertFalse($dtOtherSession->rowExists(4));
            $dtOtherSession && $this->assertNotEquals('evenNewerValue', $dtOtherSession[20][self::STRING_COLUMN]);
            $dtOtherSession && $this->assertTrue($dtOtherSession->rowExists(21));

            $this->assertTrue($dataTable->rollBack());

            foreach( $dataTables as $dt) {
                $dt && $this->assertFalse($dt->isInTransaction());
                $dt && $this->assertFalse($dt->isUnderlyingDatabaseInTransaction());
                $dt && $this->assertFalse($dt->rowExists(3));
                $dt && $this->assertFalse($dt->rowExists(4));
                $dt && $this->assertEquals('newValue', $dt[20][self::STRING_COLUMN]);
                $dt && $this->assertTrue($dt->rowExists(21));
            }
        } else {
            $this->assertFalse($dataTable->startTransaction());
            $this->assertFalse($dataTable->isInTransaction());
            $this->assertFalse($dataTable->commit());
            $this->assertFalse($dataTable->rollBack());
        }
    }


    protected function getLogger() : Logger {
        $logger = new Logger('Test');

        $logStream = new StreamHandler('test.log', LogLevel::DEBUG);
        $logger->pushHandler($logStream);
        return $logger;
    }
}
