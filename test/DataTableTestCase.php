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
 * Reference test cases for a GenericDataTable implementation
 *
 *
 * @author Rafael Nájera <rafael@najera.ca>
 */
abstract class DataTableTestCase extends TestCase
{
    
    public int $numRows = 100;
    public int $numIterations = 50;
    
    abstract public function createEmptyDt() : GenericDataTable;



    private function fillUpTestDataTable(GenericDataTable $dataTable) : GenericDataTable
    {
        for ($i = 1; $i <= $this->numRows; $i++) {
            $someRow = ['somekey' => $i, 'someotherkey' => "textvalue$i"];
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
        
        $dataTable = $this->createEmptyDt();
        $idColumn = $dataTable->getIdColumnName();
        
        $this->assertSame(false, $dataTable->rowExists(1));
        
        $ids = [];
        for ($i = 1; $i <= $this->numRows; $i++) {
            $someRow = [ 'somekey' => $i, 'someotherkey' => "textvalue$i"];
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
                'somekey' => $theId, 'someotherkey' => "textvalue$theId" ]);
            $this->assertSame($theId, $newId, $testMsg);
            $this->assertTrue(in_array($theId, $dataTable->getUniqueIds()), $testMsg);
        }
    }

    public function testFindSingle()
    {
        $dataTable = $this->fillUpTestDataTable($this->createEmptyDt());
        $idColumn = $dataTable->getIdColumnName();

        // Random searches
        $nSearches = $this->numIterations;
        for ($i = 0; $i < $nSearches; $i++) {
            $someInt = rand(1, $this->numRows);
            $someTextvalue = "textvalue$someInt";
            $testMsg = "Random searches,  iteration $i, int=$someInt";
            $theRows = $dataTable->findRows(['somekey' => $someInt], 1);
            $this->assertEquals(1, $theRows->count(), $testMsg);
            $this->assertTrue(is_int($theRows->getFirst()[$idColumn]), $testMsg);
            $theRows2 = $dataTable->findRows(['someotherkey' => $someTextvalue], 1);
            $this->assertEquals(1, $theRows2->count(), $testMsg);
            $this->assertEquals($theRows->getFirst()[$idColumn], $theRows2->getFirst()[$idColumn], $testMsg);
            $rowId = $dataTable->getIdForKeyValue(
                'someotherkey',
                $someTextvalue
            );
            $this->assertNotEquals(GenericDataTable::NULL_ROW_ID, $rowId, $testMsg);
            $this->assertEquals($theRows->getFirst()[$idColumn], $rowId);
            $theRows3 = $dataTable->findRows(['somekey' => $someInt,
                'someotherkey' => $someTextvalue]);
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
        $dataTable = $this->createEmptyDt();
        
        for ($i = 0; $i < $this->numRows; $i++) {
            $dataTable->createRow(['somekey' => 100]);
        }
        
        for ($i = 1; $i <= $this->numRows; $i++) {
            $this->assertEquals($i, $dataTable->findRows(['somekey' => 100], $i)->count());
        }
        
        for ($i = $this->numRows+1;
            $i <= $this->numRows+1+ $this->numIterations; $i++) {
            $this->assertEquals(
                $this->numRows,
                $dataTable->findRows(['somekey' => 100], $i)->count()
            );
        }
    }

    private function getStringValue(string $prefix, int $value) : string {
        return  $prefix . sprintf("%03d",$value);
    }

    /**
     * @throws InvalidSearchType
     * @throws RowAlreadyExists
     * @throws InvalidSearchSpec
     */
    public function testComplexSearches() {
        $dataTable = $this->createEmptyDt();
        $stringKeyName  = 'someotherkey';
        $intKeyName = 'somekey';
        $stringValuePrefix = 'value';

        for ($i = 1; $i < $this->numRows+1; $i++) {
            $dataTable->createRow([ $stringKeyName => $this->getStringValue($stringValuePrefix, $i), $intKeyName => $i]);
        }

        $testCases = [
            [
                'title' => 'No matches (equal)',
                'expectedCount' => 0,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => GenericDataTable::COND_EQUAL_TO, 'value' => 'x' . $stringValuePrefix]
                ]
            ],
            [
                'title' => 'No matches (greater than)',
                'expectedCount' => 0,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $intKeyName, 'condition' => GenericDataTable::COND_GREATER_THAN, 'value' =>  $this->numRows+1]
                ]
            ],
            [
                'title' => 'Not equal to (int)',
                'expectedCount' => 99,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $intKeyName, 'condition' => GenericDataTable::COND_NOT_EQUAL_TO, 'value' =>  1]
                ]
            ],
            [
                'title' => 'Not equal to (string)',
                'expectedCount' => 99,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => GenericDataTable::COND_NOT_EQUAL_TO, 'value' =>  $this->getStringValue($stringValuePrefix, 1)]
                ]
            ],
            [
                'title' => 'Greater than and less than (int)',
                'expectedCount' => $this->numRows - 20,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $intKeyName, 'condition' => GenericDataTable::COND_GREATER_THAN, 'value' => 10],
                    [ 'column' => $intKeyName, 'condition' => GenericDataTable::COND_LESS_OR_EQUAL_TO, 'value' => $this->numRows-10],
                ]
            ],
            [
                'title' => 'Greater than and less than (string)',
                'expectedCount' => $this->numRows - 20,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => GenericDataTable::COND_GREATER_THAN, 'value' => $this->getStringValue($stringValuePrefix, 10) ],
                    [ 'column' => $stringKeyName, 'condition' => GenericDataTable::COND_LESS_OR_EQUAL_TO, 'value' => $this->getStringValue($stringValuePrefix, $this->numRows-10)],
                ]
            ],
            [
                'title' => 'Less than or greater than (int)',
                'expectedCount' => 20,
                'searchType' => DataTable::SEARCH_OR,
                'specArray' => [
                    [ 'column' => $intKeyName, 'condition' => GenericDataTable::COND_LESS_THAN, 'value' => 11 ],
                    [ 'column' => $intKeyName, 'condition' => GenericDataTable::COND_GREATER_THAN, 'value' =>  $this->numRows-10 ],
                ]
            ],
            [
                'title' => 'Less than or greater than (string)',
                'expectedCount' => 20,
                'searchType' => DataTable::SEARCH_OR,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => GenericDataTable::COND_LESS_THAN, 'value' => $this->getStringValue($stringValuePrefix, 11) ],
                    [ 'column' => $stringKeyName, 'condition' => GenericDataTable::COND_GREATER_THAN, 'value' => $this->getStringValue($stringValuePrefix, $this->numRows-10)],
                ]
            ],
            [
                'title' => 'Greater than or equal to (int)',
                'expectedCount' => 10,
                'searchType' => DataTable::SEARCH_OR,
                'specArray' => [
                    [ 'column' => $intKeyName, 'condition' => GenericDataTable::COND_GREATER_OR_EQUAL_TO, 'value' => $this->numRows-9],
                ]
            ],
            [
                'title' => 'Greater than or equal to (string)',
                'expectedCount' => 10,
                'searchType' => DataTable::SEARCH_OR,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => GenericDataTable::COND_GREATER_OR_EQUAL_TO, 'value' => $this->getStringValue($stringValuePrefix, $this->numRows-9)],
                ]
            ]
        ];

        foreach($testCases as $testCase) {
            $resultingRows = $dataTable->search($testCase['specArray'], $testCase['searchType']);
            $this->assertEquals($testCase['expectedCount'], $resultingRows->count(), $testCase['title']);
        }
    }

    public function testBadlyFormedSearches() {
        $dataTable = $this->createEmptyDt();
        $stringKeyName  = 'someotherkey';

        $testCases = [
            [
                'title' => 'Empty Spec Array',
                'expectedErrorCode' => GenericDataTable::ERROR_INVALID_SPEC_ARRAY,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => []
            ],
            [
                'title' => 'No Column',
                'expectedErrorCode' => GenericDataTable::ERROR_INVALID_SPEC_ARRAY,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'condition' => GenericDataTable::COND_EQUAL_TO, 'value' => 'anyValue']
                ]
            ],
            [
                'title' => 'Wrong Column (int)',
                'expectedErrorCode' => GenericDataTable::ERROR_INVALID_SPEC_ARRAY,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => 32, 'condition' => GenericDataTable::COND_EQUAL_TO, 'value' => 'anyValue']
                ]
            ],
            [
                'title' => 'Bad Search Type',
                'expectedErrorCode' => GenericDataTable::ERROR_INVALID_SEARCH_TYPE,
                'searchType' => 100,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => GenericDataTable::COND_EQUAL_TO, 'value' => 'anyValue']
                ]
            ],
            [
                'title' => 'Bad condition (wrong int)',
                'expectedErrorCode' => GenericDataTable::ERROR_INVALID_SPEC_ARRAY,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => GenericDataTable::COND_EQUAL_TO + 1000, 'value' => 'anyValue']
                ]
            ],
            [
                'title' => 'Bad condition (string)',
                'expectedErrorCode' => GenericDataTable::ERROR_INVALID_SPEC_ARRAY,
                'searchType' => DataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => '1', 'value' => 'anyValue']
                ]
            ],
            [
                'title' => 'No value',
                'expectedErrorCode' => GenericDataTable::ERROR_INVALID_SPEC_ARRAY,
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
        $dataTable = $this->fillUpTestDataTable($this->createEmptyDt());
        $idColumn = $dataTable->getIdColumnName();

        $nUpdates = $this->numIterations;
        for ($i = 0; $i < $nUpdates; $i++) {
            $someInt = rand(1, $this->numRows);
            $newTextValue = "NewTextValue$someInt";
            $testMsg = "Random updates,  iteration $i, int=$someInt, new value: $newTextValue";
            $theRows = $dataTable->findRows(['somekey' => $someInt], 1);
            $this->assertEquals(1, $theRows->count(), $testMsg);
            $theRow = $theRows->getFirst();
            $theId = $theRow[$idColumn];
            $dataTable->updateRow([$idColumn=>$theId,  'someotherkey' => $newTextValue]);
            $theRow2 = $dataTable->getRow($theId);
            $this->assertEquals(
                $newTextValue,
                $theRow2['someotherkey'],
                $testMsg
            );
            $this->assertEquals($someInt, $theRow2['somekey'], $testMsg);
        }
    }


     public function testNonExistentRows()
    {
        $dataTable = $this->createEmptyDt();
        $this->assertFalse($dataTable->rowExists(1));

        try {
            $dataTable->getRow(1);
        } catch (InvalidArgumentException) {}

        $this->assertEquals(GenericDataTable::ERROR_ROW_DOES_NOT_EXIST, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());

        $this->assertEquals(0, $dataTable->findRows(['key' => 'someValue'], 1)->count());

        $this->assertEquals(GenericDataTable::NULL_ROW_ID, $dataTable->getIdForKeyValue('key', 'someValue'));
        $this->assertEquals(0, $dataTable->getAllRows()->count());
        $this->assertEquals(0, $dataTable->deleteRow(1));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testCreateRow()
    {
        $dataTable = $this->createEmptyDt();
        $idColumn = $dataTable->getIdColumnName();

        $res = $dataTable->createRow([$idColumn => 1, 'value' => 'test']);
        $this->assertEquals(1, $res);

        // Trying to create an existing row
        $exceptionCaught = false;
        try{
            $dataTable->createRow([$idColumn => 1, 'value' => 'anotherValue']);
        }
        catch (InvalidArgumentException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(GenericDataTable::ERROR_ROW_ALREADY_EXISTS, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());
        $row = $dataTable->getRow(1);
        $this->assertEquals('test', $row['value']);

        // invalid ID: a new one must be generated
        $newId = $dataTable->createRow([$idColumn => 'notaNumber', 'value' => 'test']);
        $this->assertNotEquals(1, $newId);

        // no ID: a new one must be generated
        $newId2 = $dataTable->createRow(['value' => 'test2']);
        $this->assertNotEquals(1, $newId2);
        $this->assertNotEquals($newId, $newId2);

    }

    /**
     * @throws RowAlreadyExists
     * @throws RowDoesNotExist
     */
    public function testUpdateRow()
    {
        $dataTable = $this->createEmptyDt();
        $idColumn = $dataTable->getIdColumnName();
        $theRow = [
            $idColumn => 1,
            'value' => 'test',
            'somekey' => 0,
            'someotherkey' => 0
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
        $this->assertEquals(GenericDataTable::ERROR_ID_NOT_SET, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());


        // no id in row
        $exceptionCaught = false;
        try {
            $dataTable->updateRow(['value' => 'testUpdate']);
        } catch (InvalidArgumentException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(GenericDataTable::ERROR_ID_NOT_SET, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());

        // Check that not updates were made!
        $updatedRow = $dataTable->getRow(1);
        $this->assertEquals($theRow, $updatedRow);
        
        // id 0, which is invalid
        $exceptionCaught = false;
        try {
            $dataTable->updateRow([$idColumn=> 0, 'value' => 'testUpdate']);
        } catch (InvalidArgumentException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(GenericDataTable::ERROR_ID_IS_ZERO, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());

        // Check that no updates were made!
        $updatedRow = $dataTable->getRow(1);
        $this->assertEquals($theRow, $updatedRow);

        // Id not integer
        $exceptionCaught = false;
        try {
            $dataTable->updateRow([$idColumn=> '1', 'value' => 'testUpdate']);
        } catch (InvalidArgumentException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(GenericDataTable::ERROR_ID_NOT_INTEGER, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());

        // Check that no updates were made!
        $updatedRow = $dataTable->getRow(1);
        $this->assertEquals($theRow, $updatedRow);

        // Row does not exist
        $exceptionCaught = false;
        try {
            $dataTable->updateRow([$idColumn=> 2, 'value' => 'testUpdate']);
        } catch (InvalidArgumentException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(GenericDataTable::ERROR_ROW_DOES_NOT_EXIST, $dataTable->getErrorCode());
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
        $dataTable = $this->createEmptyDt();
        $idColumn = $dataTable->getIdColumnName();
        $theRow = [$idColumn => 1, 'value' => 'test'];
        
        $res = $dataTable->createRow($theRow);
        $this->assertEquals(1, $res);
        
        $this->assertTrue($dataTable->rowExists(1));
        $this->assertFalse($dataTable->rowExists(0));
    }

    public function testArrayAccess() {
        $dataTable = $this->createEmptyDt();
        $idColumn = $dataTable->getIdColumnName();


        $dataTable[] = [ $idColumn => 1, 'value' => 'one'];
        $dataTable[] = [ $idColumn => 2, 'value' => 'two'];
        $dataTable[25]  = ['value' => 25];

        $this->assertEquals('one', $dataTable[1]['value']);
        $this->assertEquals('two', $dataTable[2]['value']);
        $this->assertEquals(25, $dataTable[25]['value']);
        $this->assertFalse(isset($dataTable[20]));
    }


    /**
     * @throws RowAlreadyExists
     * @throws RowDoesNotExist
     * @throws InvalidRowForUpdate
     */
    public function testEscaping()
    {
        $dataTable = $this->createEmptyDt();
        $idColumn = $dataTable->getIdColumnName();

        $rowId = $dataTable->createRow(['somekey' => 120]);
        $theRow = $dataTable->getRow($rowId);
        $this->assertSame($rowId, $theRow[$idColumn]);
        $this->assertEquals(120, $theRow['somekey']);
        $this->assertFalse(isset($theRow['someotherkey']));
        $dataTable->updateRow([$idColumn => $rowId, 'somekey' => null,
            'someotherkey' => 'Some string']);
        $theRow2 = $dataTable->getRow($rowId);
        $this->assertTrue(is_null($theRow2['somekey']));
        $this->assertEquals('Some string', $theRow2['someotherkey']);

    }


    protected function getLogger() : Logger {
        $logger = new Logger('Test');

        $logStream = new StreamHandler('test.log', LogLevel::DEBUG);
        $logger->pushHandler($logStream);
        return $logger;
    }
}
