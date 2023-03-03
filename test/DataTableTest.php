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

use InvalidArgumentException;
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
abstract class DataTableTest extends TestCase
{
    
    public $numRows = 100;
    public $numIterations = 50;
    
    abstract public function createEmptyDt() : GenericDataTable;
    
    private function fillUpTestDataTable(GenericDataTable $dataTable) : GenericDataTable
    {
        for ($i = 1; $i <= $this->numRows; $i++) {
            $someRow = ['somekey' => $i, 'someotherkey' => "textvalue$i"];
            $dataTable->createRow($someRow);
        }
        return $dataTable;
    }
    
    public function testCreationAndDeletion()
    {
        
        $dataTable = $this->createEmptyDt();
        
        $this->assertSame(false, $dataTable->rowExists(1));
        
        $ids = [];
        for ($i = 1; $i <= $this->numRows; $i++) {
            $someRow = [ 'somekey' => $i, 'someotherkey' => "textvalue$i"];
            $testMsg = "Creating rows, iteration $i";
            $newId = $dataTable->createRow($someRow);
            $this->assertNotFalse($newId, $testMsg);
            $this->assertTrue($dataTable->rowExists($newId), $testMsg);
            $ids[] = $newId;
        }

        sort($ids, SORT_NUMERIC);

        $this->assertEquals($ids, $dataTable->getUniqueIds());

        // Some random deletions and additions
        for ($i = 0; $i < $this->numIterations; $i++) {
            $theId = $ids[rand(0, $this->numRows-1)];
            $testMsg = "Random deletions and additions,  iteration $i, "
                    . "id=$theId";
            $this->assertTrue($dataTable->rowExists($theId), $testMsg);
            $this->assertEquals(1, $dataTable->deleteRow($theId), $testMsg);
            $this->assertFalse($dataTable->rowExists($theId), $testMsg);
            $this->assertFalse(in_array($theId, $dataTable->getUniqueIds()), $testMsg);
            $newId = $dataTable->createRow([ 'id' => $theId,
                'somekey' => $theId, 'someotherkey' => "textvalue$theId" ]);
            $this->assertNotFalse($newId, $testMsg);
            $this->assertSame($theId, $newId, $testMsg);
            $this->assertTrue(in_array($theId, $dataTable->getUniqueIds()), $testMsg);
        }
    }
    
    public function testFindSingle()
    {
        $dataTable = $this->fillUpTestDataTable($this->createEmptyDt());

        // Random searches
        $nSearches = $this->numIterations;
        for ($i = 0; $i < $nSearches; $i++) {
            $someInt = rand(1, $this->numRows);
            $someTextvalue = "textvalue$someInt";
            $testMsg = "Random searches,  iteration $i, int=$someInt";
            $theRows = $dataTable->findRows(['somekey' => $someInt], 1);
            $this->assertCount(1, $theRows, $testMsg);
            $this->assertTrue(is_int($theRows[0]['id']), $testMsg);
            $theRows2 = $dataTable->findRows(['someotherkey' => $someTextvalue], 1);
            $this->assertCount(1, $theRows2, $testMsg);
            $this->assertEquals($theRows[0]['id'], $theRows2[0]['id'], $testMsg);
            $rowId = $dataTable->getIdForKeyValue(
                'someotherkey',
                $someTextvalue
            );
            $this->assertNotEquals(GenericDataTable::NULL_ROW_ID, $rowId, $testMsg);
            $this->assertEquals($theRows[0]['id'], $rowId);
            $theRows3 = $dataTable->findRows(['somekey' => $someInt,
                'someotherkey' => $someTextvalue]);
            $this->assertCount(1, $theRows3, $testMsg);

            $this->assertEquals($theRows[0]['id'], $theRows3[0]['id'], $testMsg);
            $this->assertTrue(is_int($theRows3[0]['id']), $testMsg);
        }
    }
    
    public function testFindMultiple()
    {
        $dataTable = $this->createEmptyDt();
        
        for ($i = 0; $i < $this->numRows; $i++) {
            $dataTable->createRow(['somekey' => 100]);
        }
        
        for ($i = 1; $i <= $this->numRows; $i++) {
            $this->assertCount($i, $dataTable->findRows(['somekey' => 100], $i));
        }
        
        for ($i = $this->numRows+1;
            $i <= $this->numRows+1+ $this->numIterations; $i++) {
            $this->assertCount(
                $this->numRows,
                $dataTable->findRows(['somekey' => 100], $i)
            );
        }
    }

    private function getStringValue(string $prefix, int $value) : string {
        return  $prefix . sprintf("%03d",$value);
    }
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
                'searchType' => GenericDataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => GenericDataTable::COND_EQUAL_TO, 'value' => 'x' . $stringValuePrefix]
                ]
            ],
            [
                'title' => 'No matches (greater than)',
                'expectedCount' => 0,
                'searchType' => GenericDataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $intKeyName, 'condition' => GenericDataTable::COND_GREATER_THAN, 'value' =>  $this->numRows+1]
                ]
            ],
            [
                'title' => 'Not equal to (int)',
                'expectedCount' => 99,
                'searchType' => GenericDataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $intKeyName, 'condition' => GenericDataTable::COND_NOT_EQUAL_TO, 'value' =>  1]
                ]
            ],
            [
                'title' => 'Not equal to (string)',
                'expectedCount' => 99,
                'searchType' => GenericDataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => GenericDataTable::COND_NOT_EQUAL_TO, 'value' =>  $this->getStringValue($stringValuePrefix, 1)]
                ]
            ],
            [
                'title' => 'Greater than and less than (int)',
                'expectedCount' => $this->numRows - 20,
                'searchType' => GenericDataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $intKeyName, 'condition' => GenericDataTable::COND_GREATER_THAN, 'value' => 10],
                    [ 'column' => $intKeyName, 'condition' => GenericDataTable::COND_LESS_OR_EQUAL_TO, 'value' => $this->numRows-10],
                ]
            ],
            [
                'title' => 'Greater than and less than (string)',
                'expectedCount' => $this->numRows - 20,
                'searchType' => GenericDataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => GenericDataTable::COND_GREATER_THAN, 'value' => $this->getStringValue($stringValuePrefix, 10) ],
                    [ 'column' => $stringKeyName, 'condition' => GenericDataTable::COND_LESS_OR_EQUAL_TO, 'value' => $this->getStringValue($stringValuePrefix, $this->numRows-10)],
                ]
            ],
            [
                'title' => 'Less than or greater than (int)',
                'expectedCount' => 20,
                'searchType' => GenericDataTable::SEARCH_OR,
                'specArray' => [
                    [ 'column' => $intKeyName, 'condition' => GenericDataTable::COND_LESS_THAN, 'value' => 11 ],
                    [ 'column' => $intKeyName, 'condition' => GenericDataTable::COND_GREATER_THAN, 'value' =>  $this->numRows-10 ],
                ]
            ],
            [
                'title' => 'Less than or greater than (string)',
                'expectedCount' => 20,
                'searchType' => GenericDataTable::SEARCH_OR,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => GenericDataTable::COND_LESS_THAN, 'value' => $this->getStringValue($stringValuePrefix, 11) ],
                    [ 'column' => $stringKeyName, 'condition' => GenericDataTable::COND_GREATER_THAN, 'value' => $this->getStringValue($stringValuePrefix, $this->numRows-10)],
                ]
            ],
            [
                'title' => 'Greater than or equal to (int)',
                'expectedCount' => 10,
                'searchType' => GenericDataTable::SEARCH_OR,
                'specArray' => [
                    [ 'column' => $intKeyName, 'condition' => GenericDataTable::COND_GREATER_OR_EQUAL_TO, 'value' => $this->numRows-9],
                ]
            ],
            [
                'title' => 'Greater than or equal to (string)',
                'expectedCount' => 10,
                'searchType' => GenericDataTable::SEARCH_OR,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => GenericDataTable::COND_GREATER_OR_EQUAL_TO, 'value' => $this->getStringValue($stringValuePrefix, $this->numRows-9)],
                ]
            ]
        ];

        foreach($testCases as $testCase) {
            $resultingRows = $dataTable->search($testCase['specArray'], $testCase['searchType']);
            $this->assertCount($testCase['expectedCount'], $resultingRows, $testCase['title']);
        }
    }

    public function testBadlyFormedSearches() {
        $dataTable = $this->createEmptyDt();
        $stringKeyName  = 'someotherkey';

        $testCases = [
            [
                'title' => 'Empty Spec Array',
                'expectedErrorCode' => GenericDataTable::ERROR_INVALID_SPEC_ARRAY,
                'searchType' => GenericDataTable::SEARCH_AND,
                'specArray' => []
            ],
            [
                'title' => 'No Column',
                'expectedErrorCode' => GenericDataTable::ERROR_INVALID_SPEC_ARRAY,
                'searchType' => GenericDataTable::SEARCH_AND,
                'specArray' => [
                    [ 'condition' => GenericDataTable::COND_EQUAL_TO, 'value' => 'anyValue']
                ]
            ],
            [
                'title' => 'Wrong Column (int)',
                'expectedErrorCode' => GenericDataTable::ERROR_INVALID_SPEC_ARRAY,
                'searchType' => GenericDataTable::SEARCH_AND,
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
                'searchType' => GenericDataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => GenericDataTable::COND_EQUAL_TO + 1000, 'value' => 'anyValue']
                ]
            ],
            [
                'title' => 'Bad condition (string)',
                'expectedErrorCode' => GenericDataTable::ERROR_INVALID_SPEC_ARRAY,
                'searchType' => GenericDataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => '1', 'value' => 'anyValue']
                ]
            ],
            [
                'title' => 'No value',
                'expectedErrorCode' => GenericDataTable::ERROR_INVALID_SPEC_ARRAY,
                'searchType' => GenericDataTable::SEARCH_AND,
                'specArray' => [
                    [ 'column' => $stringKeyName, 'condition' => '1']
                ]
            ]
        ];

        foreach($testCases as $testCase) {
            $exceptionCaught = false;
            try {
                $dataTable->search($testCase['specArray'], $testCase['searchType']);
            } catch (InvalidArgumentException $e) {
                $exceptionCaught = true;
            }
            $this->assertTrue($exceptionCaught, $testCase['title']);
            $this->assertEquals($testCase['expectedErrorCode'], $dataTable->getErrorCode(), $testCase['title']);
        }

    }
    
    public function testUpdate()
    {
        $dataTable = $this->fillUpTestDataTable($this->createEmptyDt());
        $nUpdates = $this->numIterations;
        for ($i = 0; $i < $nUpdates; $i++) {
            $someInt = rand(1, $this->numRows);
            $newTextValue = "NewTextValue$someInt";
            $testMsg = "Random updates,  iteration $i, int=$someInt, new value: $newTextValue";
            $theRows = $dataTable->findRows(['somekey' => $someInt], 1);
            $this->assertCount(1, $theRows, $testMsg);
            $theRow = $theRows[0];
            $theId = $theRow['id'];
            $dataTable->updateRow(['id'=>$theId,  'someotherkey' => $newTextValue]);
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

        $exceptionCaught = false;
        try {
            $dataTable->getRow(1);
        } catch(InvalidArgumentException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(GenericDataTable::ERROR_ROW_DOES_NOT_EXIST, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());

        $this->assertEquals([], $dataTable->findRows(['key' => 'somevalue'], 1));

        $this->assertEquals(GenericDataTable::NULL_ROW_ID, $dataTable->getIdForKeyValue('key', 'somevalue'));
        $this->assertEquals([], $dataTable->getAllRows());
        $this->assertEquals(0,$dataTable->deleteRow(1));
    }
    
    public function testCreateRow()
    {
        $dataTable = $this->createEmptyDt();

        
        $res = $dataTable->createRow(['id' => 1, 'value' => 'test']);
        $this->assertEquals(1, $res);

        // Trying to create an existing row
        $exceptionCaught = false;
        try{
            $dataTable->createRow(['id' => 1, 'value' => 'anothervalue']);
        }
        catch (InvalidArgumentException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(GenericDataTable::ERROR_ROW_ALREADY_EXISTS, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());
        $row = $dataTable->getRow(1);
        $this->assertEquals('test', $row['value']);

        // invalid Id: a new one must be generated
        $newId = $dataTable->createRow(['id' => 'notanumber', 'value' => 'test']);
        $this->assertNotEquals(1, $newId);

        // no Id: a new one must be generated
        $newId2 = $dataTable->createRow(['value' => 'test2']);
        $this->assertNotEquals(1, $newId2);
        $this->assertNotEquals($newId, $newId2);

    }
    
    public function testUpdateRow()
    {
        $dataTable = $this->createEmptyDt();
        $theRow = [
            'id' => 1,
            'value' => 'test',
            'somekey' => 0,
            'someotherkey' => 0
        ];
        $res = $dataTable->createRow($theRow);
        $this->assertEquals(1, $res);
        
        // No Id in row
        $exceptionCaught = false;
        try {
            $dataTable->updateRow([]);
        } catch (InvalidArgumentException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(GenericDataTable::ERROR_ID_NOT_SET, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());


        // no id in row
        $exceptionCaught = false;
        try {
            $dataTable->updateRow(['value' => 'testUpdate']);
        } catch (InvalidArgumentException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(GenericDataTable::ERROR_ID_NOT_SET, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());

        // Check that not updates were made!
        $updatedRow = $dataTable->getRow(1);
        $this->assertEquals($theRow, $updatedRow);
        
        // Id 0, which is invalid
        $exceptionCaught = false;
        try {
            $dataTable->updateRow(['id'=> 0, 'value' => 'testUpdate']);
        } catch (InvalidArgumentException $e) {
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
            $dataTable->updateRow(['id'=> '1', 'value' => 'testUpdate']);
        } catch (InvalidArgumentException $e) {
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
            $dataTable->updateRow(['id'=> 2, 'value' => 'testUpdate']);
        } catch (InvalidArgumentException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(GenericDataTable::ERROR_ROW_DOES_NOT_EXIST, $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());

        // Check that no updates were made!
        $updatedRow = $dataTable->getRow(1);
        $this->assertEquals($theRow, $updatedRow);
    }
    
    public function testRowExistsById()
    {
        $dataTable = $this->createEmptyDt();
        $theRow = ['id' => 1, 'value' => 'test'];
        
        $res = $dataTable->createRow($theRow);
        $this->assertEquals(1, $res);
        
        $this->assertTrue($dataTable->rowExists(1));
        $this->assertFalse($dataTable->rowExists(0));
    }
    
    
    public function testEscaping()
    {
        $dataTable = $this->createEmptyDt();

        $rowId = $dataTable->createRow(['somekey' => 120]);
        $theRow = $dataTable->getRow($rowId);
        $this->assertSame($rowId, $theRow['id']);
        $this->assertEquals(120, $theRow['somekey']);
        $this->assertFalse(isset($theRow['someotherkey']));
        $dataTable->updateRow(['id' => $rowId, 'somekey' => null,
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
