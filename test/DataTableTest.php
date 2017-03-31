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
namespace DataTable;

use PHPUnit\Framework\TestCase;

/**
 * Description of DataTableTest
 *
 * @author Rafael Nájera <rafael@najera.ca>
 */
abstract class DataTableTest extends TestCase
{
    
    public $numRows = 100;
    public $numIterations = 50;
    
    abstract public function createEmptyDt();
    
    private function fillUpTestDataTable($dataTable)
    {
        for ($i = 1; $i <= $this->numRows; $i++) {
            $someRow = ['somekey' => $i, 'someotherkey' => "textvalue$i"];
            $dataTable->createRow($someRow);
        }
        return $dataTable;
    }
    
    public function testCreation()
    {
        
        $dataTable = $this->createEmptyDt();
        
        $this->assertSame(false, $dataTable->rowExistsById(1));
        
        $ids = array();
        for ($i = 1; $i <= $this->numRows; $i++) {
            $someRow = [ 'somekey' => $i, 'someotherkey' => "textvalue$i"];
            $testMsg = "Creating rows, iteration $i";
            $newId = $dataTable->createRow($someRow);
            $this->assertNotFalse($newId, $testMsg);
            $this->assertTrue($dataTable->rowExistsById($newId), $testMsg);
            array_push($ids, $newId);
        }

        // Some random deletions and additions
        for ($i = 0; $i < $this->numIterations; $i++) {
            $theId = $ids[rand(0, $this->numRows-1)];
            $testMsg = "Random deletions and additions,  iteration $i, "
                    . "id=$theId";
            $this->assertTrue($dataTable->rowExistsById($theId), $testMsg);
            $this->assertTrue($dataTable->deleteRow($theId), $testMsg);
            $this->assertFalse($dataTable->rowExistsById($theId), $testMsg);
            $newId = $dataTable->createRow([ 'id' => $theId,
                'somekey' => $theId, 'someotherkey' => "textvalue$theId" ]);
            $this->assertNotFalse($newId, $testMsg);
            $this->assertSame($theId, $newId, $testMsg);
        }
    }
    
    public function testFindSingle()
    {
        $dataTable = $this->fillUpTestDataTable($this->createEmptyDt());

        $this->assertFalse($dataTable->findRows(['it' => 'doesntmatter'], -1));
        $this->assertFalse($dataTable->findRows(['it' => 'doesntmatter'], 0));
        
        // Random searches
        $nSearches = $this->numIterations;
        for ($i = 0; $i < $nSearches; $i++) {
            $someInt = rand(1, $this->numRows);
            $someTextvalue = "textvalue$someInt";
            $testMsg = "Random searches,  iteration $i, int=$someInt";
            $theRow = $dataTable->findRow(['somekey' => $someInt]);
            $this->assertNotFalse($theRow, $testMsg);
            $this->assertTrue(is_int($theRow['id']));
            $theRow2 = $dataTable->findRow(['someotherkey' => $someTextvalue]);
            $this->assertNotFalse($theRow, $testMsg);
            $this->assertEquals($theRow['id'], $theRow2['id'], $testMsg);
            $theRow3 = $dataTable->findRow(['somekey' => $someInt,
                'someotherkey' => $someTextvalue]);
            $this->assertNotFalse($theRow3, $testMsg);
            $this->assertEquals($theRow['id'], $theRow3['id'], $testMsg);
            $this->assertTrue(is_int($theRow['id']));
        }
    }
    
    public function testFindMultiple()
    {
        $dataTable = $this->createEmptyDt();
        
        for ($i = 0; $i < $this->numRows; $i++) {
            $this->assertNotFalse($dataTable->createRow(['somekey' => 100]));
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
    
    public function testUpdate()
    {
        $dataTable = $this->fillUpTestDataTable($this->createEmptyDt());
        $nUpdates = $this->numIterations;
        for ($i = 0; $i < $nUpdates; $i++) {
            $someInt = rand(1, $this->numRows);
            $newTextValue = "NewTextValue$someInt";
            $testMsg = "Random updates,  iteration $i, int=$someInt";
            $theRow = $dataTable->findRow(['somekey' => $someInt]);
            $this->assertNotFalse($theRow, $testMsg);
            $theId = $theRow['id'];
            $this->assertNotFalse($dataTable->updateRow(['id'=>$theId,
                'someotherkey' => $newTextValue]));
            $theRow2 = $dataTable->getRow($theId);
            $this->assertNotFalse($theRow2);
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
        $this->assertFalse($dataTable->rowExistsById(1));
        $this->assertFalse($dataTable->getRow(1));
        $this->assertFalse($dataTable->findRow(['key' => 'somevalue']));
        $this->assertFalse($dataTable->getIdForKeyValue('key', 'somevalue'));
        $this->assertEquals([], $dataTable->getAllRows());
        $this->assertTrue($dataTable->deleteRow(1));
    }
    
    public function testCreateRow()
    {
        $dataTable = $this->createEmptyDt();
        // Id not integer
        $res = $dataTable->createRow(['id' => 'notanumber', 'value' => 'test']);
        $this->assertFalse($res);
        
        
        $res = $dataTable->createRow(['id' => 1, 'value' => 'test']);
        $this->assertEquals(1, $res);
        // Trying to create an existing row
        $res = $dataTable->createRow(['id' => 1, 'value' => 'anothervalue']);
        $this->assertFalse($res);
        $row = $dataTable->getRow(1);
        $this->assertEquals('test', $row['value']);
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
        
        $this->assertFalse($dataTable->updateRow([]));
        // No Id in row
        $this->assertFalse($dataTable->updateRow(['value' => 'testUpdate']));
        $updatedRow = $dataTable->getRow(1);
        $this->assertEquals($theRow, $updatedRow);
        
        // Id 0, which is invalid
        $res = $dataTable->updateRow(['id'=> 0, 'value' => 'testUpdate']);
        $this->assertFalse($res);
        $updatedRow = $dataTable->getRow(1);
        $this->assertEquals($theRow, $updatedRow);
        
        // Id not integer
        $res = $dataTable->updateRow(['id'=> '1', 'value' => 'testUpdate']);
        $this->assertFalse($res);
        $updatedRow = $dataTable->getRow(1);
        $this->assertEquals($theRow, $updatedRow);
        
        // Row doesn't exist
        $res = $dataTable->updateRow(['id'=> 2, 'value' => 'testUpdate']);
        $this->assertFalse($res);
        $updatedRow = $dataTable->getRow(1);
        $this->assertEquals($theRow, $updatedRow);
    }
    
    public function testRowExistsById()
    {
        $dataTable = $this->createEmptyDt();
        $theRow = ['id' => 1, 'value' => 'test'];
        
        $res = $dataTable->createRow($theRow);
        $this->assertEquals(1, $res);
        
        $this->assertTrue($dataTable->rowExistsById(1));
        $this->assertTrue($dataTable->rowExistsById('1'));
        $this->assertFalse($dataTable->rowExistsById('b'));
        $this->assertFalse($dataTable->rowExistsById(0));
    }
    
    
    public function testEscaping()
    {
        $dataTable = $this->createEmptyDt();

        $rowId = $dataTable->createRow(['somekey' => 120]);
        $this->assertNotFalse($rowId);
        $theRow = $dataTable->getRow($rowId);
        $this->assertSame($rowId, $theRow['id']);
        $this->assertEquals(120, $theRow['somekey']);
        $this->assertFalse(isset($theRow['someotherkey']));
        $id2 = $dataTable->updateRow(['id' => $rowId, 'somekey' => null,
            'someotherkey' => 'Some string']);
        $this->assertSame($rowId, $id2);
        $theRow2 = $dataTable->getRow($id2);
        $this->assertSame($id2, $theRow2['id']);
        $this->assertEquals(null, $theRow2['somekey']);
        $this->assertSame('Some string', $theRow2['someotherkey']);
    }
}
