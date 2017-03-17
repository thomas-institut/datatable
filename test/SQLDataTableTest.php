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
require 'config.php';

use PHPUnit\Framework\TestCase;
use \PDO;
/**
 * Description of SQLDataTableTest
 *
 * @author Rafael Nájera <rafael.najera@uni-koeln.de>
 */
class SQLDataTableTest extends TestCase {
    
    var $numRows = 100;
    
    public function getPdo() 
    {
        global $config;
        
        return new PDO('mysql:dbname=' . $config['db'] . 
                ';host=' . $config['host'], $config['user'], 
                $config['pwd']);
        
    }
    
    public function resetTestDb($pdo)
    {
        $tableSetupSQL =<<<EOD
            DROP TABLE IF EXISTS `testtable`;
            CREATE TABLE IF NOT EXISTS `testtable` (
              `id` int(11) UNSIGNED NOT NULL,
              `somekey` int(11) DEFAULT NULL,
              `someotherkey` varchar(100) DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
EOD;
        $pdo->query($tableSetupSQL);
        
    }
    
    public function test1()
    {
        $pdo = $this->getPdo();
        $this->resetTestDb($pdo);
        $dt = new MySqlDataTable($pdo, 'testtable');
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
    }
    
     /**
     * 
     * @depends test1
     */
    function testEscaping()
    {
        
        $pdo = $this->getPdo();
        $dt = new MySqlDataTable($pdo, 'testtable');
        // somekey is supposed to be an integer
        $id = $dt->createRow(['somekey' => 'A string']);
        $this->assertSame(false, $id);
        
        // This should work
        $id = $dt->createRow(['somekey' => 120]);
        $this->assertNotSame(false, $id);
        $theRow = $dt->getRow($id);
        $this->assertSame($id, $theRow['id']);
        $this->assertEquals(120, $theRow['somekey']);
        $this->assertSame(NULL, $theRow['someotherkey']);
        $id2 = $dt->updateRow(['id' => $id, 'somekey' => NULL, 'someotherkey' => 'Some string']);
        $this->assertSame($id, $id2);
        $theRow2 = $dt->getRow($id2);
        $this->assertSame($id2, $theRow2['id']);
        $this->assertEquals(NULL, $theRow2['somekey']);
        $this->assertSame('Some string', $theRow2['someotherkey']);

    }
    
    /**
     * 
     * @depends test1
     */
    function testFind()
    {
        $pdo = $this->getPdo();
        $dt = new MySqlDataTable($pdo, 'testtable');
        $nSearches = 100;
        for ($i = 0; $i < $nSearches; $i++){
            $someInt = rand(1, $this->numRows);
            $someTextvalue = "textvalue$someInt";
            $testMsg = "Random searches,  iteration $i, int=$someInt";
            $theId = $dt->getIdForKeyValue('somekey', $someInt);
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
     * @depends test1
     */
    function testFindDuplicates()
    {
        $pdo = $this->getPdo();
        $dt = new MySqlDataTable($pdo, 'testtable');
        $someString = 'This string should not be in any row';
        
        $nDuplicates = 10;
        $ids = array();
        for ($i = 0; $i < $nDuplicates; $i++){
            $id =$dt->createRow(['someotherkey' => $someString]);
            $this->assertNotSame(false, $id);
            array_push($ids, $id);
        }
        
        $foundIds = $dt->findRows(['someotherkey' => $someString]);
        $this->assertEquals(count($ids), count($foundIds));
        foreach ($foundIds as $foundId){
            $this->assertNotSame(false, array_search($foundId, $ids));
        }
        
        $singleId = $dt->findRow(['someotherkey' => $someString]);
        $this->assertNotSame(false, $singleId);
        $this->assertNotSame(false, array_search($singleId, $ids));
        
    }
 
    /**
     * `
     * @depends test1
     */
    public function testUpdate()
    {
        $pdo = $this->getPdo();
        $dt = new MySqlDataTable($pdo, 'testtable');
        $nUpdates = 100;
        for ($i = 0; $i < $nUpdates; $i++){
            $someInt = rand(1, $this->numRows);
            $newTextValue = "NewTextValue$someInt";
            $theId = $dt->findRow(['somekey' => $someInt]);
            // Can't assume that the randon int will be found in the data
            if ($theId === false){
                continue;
            }
            $testMsg = "Random updates,  iteration $i, int=$someInt, id=$theId";
            $this->assertNotSame(false, $theId, $testMsg);
            $this->assertNotSame(false, $dt->updateRow(['id'=>$theId, 'someotherkey' => $newTextValue]), $testMsg);
            $theRow = $dt->getRow($theId);
            $this->assertNotSame(false, $theRow);
            $this->assertSame($newTextValue, $theRow['someotherkey']);
            $this->assertEquals($someInt,  $theRow['somekey']);
            $someOtherInt = rand(1, 10000);
            $this->assertNotSame(false, $dt->updateRow(['id'=>$theId, 'somekey' => $someOtherInt]), $testMsg);
            $theRow = $dt->getRow($theId);
            $this->assertEquals($someOtherInt,  $theRow['somekey']);
            $this->assertEquals($newTextValue, $theRow['someotherkey']);
            $this->assertSame(false, $dt->updateRow(['id'=>$theId, 'nonexistentkey' => $someOtherInt]), $testMsg);
            
        }
    }
    
    /**
     * @depends test1
     */
    function testRandomIds ()
    {
        $minId = 100000;
        $maxId = 200000;
        
        $pdo = $this->getPdo();
        
        $dt = new MySqlDataTableWithRandomIds($pdo, 'testtable', $minId, $maxId);
       
        // Adding new rows
        $nRows = 10;
        for ($i = 0; $i < $nRows; $i++){
            $newID = $dt->createRow([ 'somekey' => $i, 'someotherkey' => "textvalue$i"] );
            $this->assertGreaterThanOrEqual($minId, $newID);
            $this->assertLessThanOrEqual($maxId, $newID);
        }

        // Trying to add rows with random Ids, but the Ids are all already taken,
        // new IDs should be greater than the rows constructed in the first test.
        $nRows = 10;
        $dt2 = new MySqlDataTableWithRandomIds($pdo, 'testtable', 1, $this->numRows);
        for ($i = 0; $i < $nRows; $i++){
            $newID = $dt2->createRow([ 'somekey' => $i, 'someotherkey' => "textvalue$i"] );
            $this->assertNotSame(false, $newID);
            $this->assertGreaterThan($this->numRows, $newID);
        }
    }
    
    public function testGetAllRows()
    {
        $pdo = $this->getPdo();
        $dt = new MySqlDataTable($pdo, 'testtable');
        
        $rows = $dt->getAllRows();
        $this->assertNotFalse($rows);
        $this->assertNotEquals([], $rows);
    }
}
