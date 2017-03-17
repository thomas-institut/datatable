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
class DataTableTest extends TestCase {
    
    var $numRows = 100;
    
    function testInMemoryDataTableCreation() {
        $dt = new InMemoryDataTable();
        $this->assertSame(false, $dt->rowExistsById(1));
        
        $ids = array();
        for ($i = 1 ; $i <= $this->numRows; $i++){
            $someRow = [ 'somekey' => $i, 'someotherkey' => "textvalue$i"];
            $testMsg = "Creating rows, iteration $i";
            $newId = $dt->createRow($someRow);
            $this->assertNotEquals(false, $newId, $testMsg);
            $this->assertSame(true, $dt->rowExistsById($newId), $testMsg);
            array_push($ids, $newId);
        }

        // Some random deletions and additions
         $nIterations = $this->numRows/10;
        for ($i = 0; $i < $nIterations; $i++){
            $theId = $ids[rand(0, $this->numRows-1)];
            $testMsg = "Random deletions and additions,  iteration $i, id=$theId";
            $this->assertSame(true, $dt->rowExistsById($theId), $testMsg);
            $this->assertSame(true, $dt->deleteRow($theId), $testMsg);
            $this->assertSame(false, $dt->rowExistsById($theId), $testMsg);
            $newId = $dt->createRow([ 'id' => $theId, 'somekey' => $theId,'someotherkey' => "textvalue$theId" ]);
            $this->assertNotEquals(false, $newId, $testMsg );
            $this->assertSame($theId, $newId, $testMsg);
            
        }
        return $dt;
    }
    
    /**
     * 
     * @depends testInMemoryDataTableCreation
     */
    function testFind(InMemoryDataTable $dt){
        $nSearches = 100;
        for ($i = 0; $i < $nSearches; $i++){
            $someInt = rand(1, $this->numRows);
            $someTextvalue = "textvalue$someInt";
            $testMsg = "Random searches,  iteration $i, int=$someInt";
            $theId = $dt->findRow(['somekey' => $someInt]);
            $this->assertNotSame(false, $theId, $testMsg);
            $theId2 = $dt->findRow(['someotherkey' => $someTextvalue]);
            $this->assertNotSame(false, $theId2, $testMsg);
            $this->assertEquals($theId, $theId2, $testMsg);
            $theId3 = $dt->findRow(['somekey' => $someInt, 'someotherkey' => $someTextvalue]);
            $this->assertNotSame(false, $theId3, $testMsg);
            $this->assertEquals($theId, $theId3, $testMsg);
        }
    }
    
    /**
     * 
     * @depends testInMemoryDataTableCreation
     */
    public function testUpdate(InMemoryDataTable $dt){
        $nUpdates = 10;
        for ($i = 0; $i < $nUpdates; $i++){
            $someInt = rand(1, $this->numRows);
            $newTextValue = "NewTextValue$someInt";
            $testMsg = "Random updates,  iteration $i, int=$someInt";
            $theId = $dt->findRow(['somekey' => $someInt]);
            $this->assertNotSame(false, $theId, $testMsg);
            $this->assertNotSame(false, $dt->updateRow(['id'=>$theId, 'someotherkey' => $newTextValue]));
            $theRow = $dt->getRow($theId);
            $this->assertNotSame(false, $theRow);
            $this->assertSame($newTextValue, $theRow['someotherkey']);
            $this->assertSame($someInt, $theRow['somekey']);
        }
    }
    
    
    public function  testNonExistentRows()
    {
        $dt = new InMemoryDataTable();
        $this->assertFalse($dt->rowExistsById(1));
        $this->assertFalse($dt->getRow(1));
        $this->assertFalse($dt->findRow(['key' => 'somevalue']));
        $this->assertFalse($dt->getIdForKeyValue('key', 'somevalue'));
        $this->assertEquals([], $dt->getAllRows());
        
    }
}
