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

require "../vendor/autoload.php";

use PHPUnit\Framework\TestCase;

/**
 * Description of DataTableTest
 *
 * @author Rafael Nájera <rafael@najera.ca>
 */
abstract class DataTableTest extends TestCase {
    
    var $numRows = 100;
    var $numIterations = 50;
    
 
    
    abstract public function createEmptyDt();
    
    private function fillUpTestDataTable($dt) 
    {
        for ($i = 1 ; $i <= $this->numRows; $i++){
            $someRow = ['somekey' => $i, 'someotherkey' => "textvalue$i"];
            $newId = $dt->createRow($someRow);
        }
        return $dt;
    }
    
    public function testCreation() {
        
        $dt = $this->createEmptyDt();
        
        $this->assertSame(false, $dt->rowExistsById(1));
        
        $ids = array();
        for ($i = 1 ; $i <= $this->numRows; $i++){
            $someRow = [ 'somekey' => $i, 'someotherkey' => "textvalue$i"];
            $testMsg = "Creating rows, iteration $i";
            $newId = $dt->createRow($someRow);
            $this->assertNotFalse($newId, $testMsg);
            $this->assertTrue($dt->rowExistsById($newId), $testMsg);
            array_push($ids, $newId);
        }

        // Some random deletions and additions
        for ($i = 0; $i < $this->numIterations; $i++){
            $theId = $ids[rand(0, $this->numRows-1)];
            $testMsg = "Random deletions and additions,  iteration $i, id=$theId";
            $this->assertTrue($dt->rowExistsById($theId), $testMsg);
            $this->assertTrue($dt->deleteRow($theId), $testMsg);
            $this->assertFalse($dt->rowExistsById($theId), $testMsg);
            $newId = $dt->createRow([ 'id' => $theId, 
                'somekey' => $theId, 'someotherkey' => "textvalue$theId" ]);
            $this->assertNotFalse($newId, $testMsg );
            $this->assertSame($theId, $newId, $testMsg);
            
        }
    }
    
    public function testFindSingle()
    {
        $dt = $this->fillUpTestDataTable($this->createEmptyDt());

        $this->assertFalse($dt->findRows(['it' => 'doesntmatter'], -1));
        $this->assertFalse($dt->findRows(['it' => 'doesntmatter'], 0));
        
        // Random searches
        $nSearches = $this->numIterations;
        for ($i = 0; $i < $nSearches; $i++){
            $someInt = rand(1, $this->numRows);
            $someTextvalue = "textvalue$someInt";
            $testMsg = "Random searches,  iteration $i, int=$someInt";
            $theRow = $dt->findRow(['somekey' => $someInt]);
            $this->assertNotFalse($theRow, $testMsg);
            $this->assertTrue(is_int($theRow['id']));
            $theRow2 = $dt->findRow(['someotherkey' => $someTextvalue]);
            $this->assertNotFalse($theRow, $testMsg);
            $this->assertEquals($theRow['id'], $theRow2['id'], $testMsg);
            $theRow3 = $dt->findRow(['somekey' => $someInt, 'someotherkey' => $someTextvalue]);
            $this->assertNotFalse($theRow3, $testMsg);
            $this->assertEquals($theRow['id'], $theRow3['id'], $testMsg);
            $this->assertTrue(is_int($theRow['id']));
        }
    }
    
    public function testFindMultiple()
    {
        $dt = $this->createEmptyDt();
        
        for ($i = 0; $i < $this->numRows; $i++) {
            $this->assertNotFalse($dt->createRow(['somekey' => 100]));
        }
        
        for ($i = 1; $i <= $this->numRows; $i++) {
            $this->assertCount($i, $dt->findRows(['somekey' => 100], $i));
        }
        
        for ($i = $this->numRows+1; 
             $i <= $this->numRows+1+ $this->numIterations; $i++) {
            $this->assertCount($this->numRows, $dt->findRows(['somekey' => 100], $i));
        }
    }
    
    public function testUpdate(){
        $dt = $this->fillUpTestDataTable($this->createEmptyDt());
        $nUpdates = $this->numIterations;
        for ($i = 0; $i < $nUpdates; $i++){
            $someInt = rand(1, $this->numRows);
            $newTextValue = "NewTextValue$someInt";
            $testMsg = "Random updates,  iteration $i, int=$someInt";
            $theRow = $dt->findRow(['somekey' => $someInt]);
            $this->assertNotFalse($theRow, $testMsg);
            $theId = $theRow['id'];
            $this->assertNotFalse($dt->updateRow(['id'=>$theId, 'someotherkey' => $newTextValue]));
            $theRow2 = $dt->getRow($theId);
            $this->assertNotFalse($theRow2);
            $this->assertEquals($newTextValue, $theRow2['someotherkey'], $testMsg);
            $this->assertEquals($someInt, $theRow2['somekey'], $testMsg);
        }
    }
    
    public function testNonExistentRows()
    {
        $dt = $this->createEmptyDt();
        $this->assertFalse($dt->rowExistsById(1));
        $this->assertFalse($dt->getRow(1));
        $this->assertFalse($dt->findRow(['key' => 'somevalue']));
        $this->assertFalse($dt->getIdForKeyValue('key', 'somevalue'));
        $this->assertEquals([], $dt->getAllRows());
        $this->assertTrue($dt->deleteRow(1));
        
    }
    
    public function testCreateRow()
    {
        $dt = $this->createEmptyDt();
        // Id not integer
        $r = $dt->createRow(['id' => 'notanumber', 'value' => 'test']);
        $this->assertFalse($r);
        
        
        $r = $dt->createRow(['id' => 1, 'value' => 'test']);
        $this->assertEquals(1, $r);
        // Trying to create an existing row
        $r = $dt->createRow(['id' => 1, 'value' => 'anothervalue']);
        $this->assertFalse($r);
        $row = $dt->getRow(1);
        $this->assertEquals('test', $row['value']);
    }
    
    public function testUpdateRow()
    {
        $dt = $this->createEmptyDt();
        $theRow = [
            'id' => 1, 
            'value' => 'test', 
            'somekey' => 0, 
            'someotherkey' => 0
        ];
        $r = $dt->createRow($theRow);
        $this->assertEquals(1, $r);
        
        $this->assertFalse($dt->updateRow([]));
        // No Id in row
        $this->assertFalse($dt->updateRow(['value' => 'testUpdate']));
        $updatedRow = $dt->getRow(1);
        $this->assertEquals($theRow, $updatedRow);
        
        // Id 0, which is invalid
        $r = $dt->updateRow(['id'=> 0, 'value' => 'testUpdate']);
        $this->assertFalse($r);
        $updatedRow = $dt->getRow(1);
        $this->assertEquals($theRow, $updatedRow);
        
        // Id not integer
        $r = $dt->updateRow(['id'=> '1', 'value' => 'testUpdate']);
        $this->assertFalse($r);
        $updatedRow = $dt->getRow(1);
        $this->assertEquals($theRow, $updatedRow);
        
        // Row doesn't exist
        $r = $dt->updateRow(['id'=> 2, 'value' => 'testUpdate']);
        $this->assertFalse($r);
        $updatedRow = $dt->getRow(1);
        $this->assertEquals($theRow, $updatedRow);
    }
    
    public function testRowExistsById()
    {
        $dt = $this->createEmptyDt();
        $theRow = ['id' => 1, 'value' => 'test'];
        
        $r = $dt->createRow($theRow);
        $this->assertEquals(1, $r);
        
        $this->assertTrue($dt->rowExistsById(1));
        $this->assertTrue($dt->rowExistsById('1'));
        $this->assertFalse($dt->rowExistsById('b'));
        $this->assertFalse($dt->rowExistsById(0));
    }
    
    
    function testEscaping()
    {
        
        $dt = $this->createEmptyDt();
        // somekey is supposed to be an integer

        //$id = $dt->createRow(['somekey' => 'A string']);
        //$this->assertSame(false, $id);
        
        // This should work
        $id = $dt->createRow(['somekey' => 120]);
        $this->assertNotFalse($id);
        $theRow = $dt->getRow($id);
        $this->assertSame($id, $theRow['id']);
        $this->assertEquals(120, $theRow['somekey']);
        $this->assertFalse(isset($theRow['someotherkey']));
        $id2 = $dt->updateRow(['id' => $id, 'somekey' => NULL, 'someotherkey' => 'Some string']);
        $this->assertSame($id, $id2);
        $theRow2 = $dt->getRow($id2);
        $this->assertSame($id2, $theRow2['id']);
        $this->assertEquals(NULL, $theRow2['somekey']);
        $this->assertSame('Some string', $theRow2['someotherkey']);
    }

}

