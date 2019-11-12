<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace DataTable;

use PDO;

require '../vendor/autoload.php';
require_once 'MySqlDataTableTest.php';

/**
 * Description of MySqlUnitemporalDataTableTest
 *
 * @author Rafael Nájera <rafael.najera@uni-koeln.de>
 */
class MySqlUnitemporalDataTableTest extends MySqlDataTableTest
{

    public function resetTestDb(PDO $pdo)
    {
        $tableSetupSQL =<<<EOD
            DROP TABLE IF EXISTS `testtable`;
            CREATE TABLE IF NOT EXISTS `testtable` (
              `id` int(11) UNSIGNED NOT NULL,
              `valid_from` datetime(6) NOT NULL,
              `valid_until` datetime(6) NOT NULL,
              `somekey` int(11) DEFAULT NULL,
              `someotherkey` varchar(100) DEFAULT NULL,
              `value` varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            ALTER TABLE `testtable` ADD PRIMARY KEY( `id`, `valid_from`, `valid_until`);
EOD;
        $pdo->query($tableSetupSQL);
    }
    
    public function resetTestDbWithBadTables(PDO $pdo)
    {
        $tableSetupSQL =<<<EOD
            DROP TABLE IF EXISTS `testtablebad1`;
            CREATE TABLE IF NOT EXISTS `testtablebad1` (
              `id` varchar(100) NOT NULL,
              `somekey` int(11) DEFAULT NULL,
              `someotherkey` varchar(100) DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            DROP TABLE IF EXISTS `testtablebad2`;                
            CREATE TABLE IF NOT EXISTS `testtablebad2` (
              `somekey` int(11) DEFAULT NULL,
              `someotherkey` varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            DROP TABLE IF EXISTS `testtablebad3`;   
            CREATE TABLE IF NOT EXISTS `testtablebad3` (
              `id` int(11) UNSIGNED NOT NULL,
              `valid_from` int(11) NOT NULL,
              `valid_until` datetime(6) NOT NULL,
              `somekey` int(11) DEFAULT NULL,
              `someotherkey` varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            DROP TABLE IF EXISTS `testtablebad4`;   
            CREATE TABLE IF NOT EXISTS `testtablebad4` (
              `id` int(11) UNSIGNED NOT NULL,
              `valid_from` datetime(6) NOT NULL,
              `valid_until` int(11) NOT NULL,
              `somekey` int(11) DEFAULT NULL,
              `someotherkey` varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;    
            DROP TABLE IF EXISTS `testtablebad5`;  
            CREATE TABLE IF NOT EXISTS `testtablebad5` (
              `id` int(11) UNSIGNED NOT NULL,
              `valid_until` datetime(6) NOT NULL,
              `somekey` int(11) DEFAULT NULL,
              `someotherkey` varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            DROP TABLE IF EXISTS `testtablebad6`;  
            CREATE TABLE IF NOT EXISTS `testtablebad6` (
              `id` int(11) UNSIGNED NOT NULL,
              `valid_from` datetime(6) NOT NULL,
              `somekey` int(11) DEFAULT NULL,
              `someotherkey` varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;  
EOD;
        $pdo->query($tableSetupSQL);
    }
    
    public function createEmptyDt() : DataTable
    {
        $pdo = $this->getPdo();
        $this->resetTestDb($pdo);
        return new MySqlUnitemporalDataTable($pdo, self::TABLE_NAME);
    }
    
      public function getRestrictedDt() : MySqlDataTable
    {
        $restrictedPdo = $this->getRestrictedPdo();
        return new MySqlUnitemporalDataTable($restrictedPdo, self::TABLE_NAME);
    }
    
    public function testBadTables()
    {

        $pdo = $this->getPdo();
        $this->resetTestDbWithBadTables($pdo);

        $exceptionCaught = false;
        try {
            $dataTable = new MySqlUnitemporalDataTable($pdo, 'testtablebad1');
        } catch(\RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);


        $exceptionCaught = false;
        try {
            $dataTable2 = new MySqlUnitemporalDataTable($pdo, 'testtablebad2');
        } catch(\RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            $dataTable3 = new MySqlUnitemporalDataTable($pdo, 'testtablebad3');
        } catch(\RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            $dataTable4 = new MySqlUnitemporalDataTable($pdo, 'testtablebad4');
        } catch(\RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            $dataTable5 = new MySqlUnitemporalDataTable($pdo, 'testtablebad5');
        } catch(\RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            $dataTable6 = new MySqlUnitemporalDataTable($pdo, 'testtablebad6');
        } catch(\RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            $dataTable7 = new MySqlUnitemporalDataTable($pdo, 'nonexistenttable');
        } catch(\RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
    }

    public function testGetTimeStringFromVariable() {

        $badVars = [ '', [], '2019-01-01.', '2019-01-01.123123', '2019-23-12', '2019-01-01 35:00:00'];

        foreach($badVars as $var) {
            $this->assertEquals('', MySqlUnitemporalDataTable::getTimeStringFromVariable($var), 'Testing ' . print_r($var, true));
        }

        $goodVars = [ -1, '2010-11-11', '2020-10-10 13:05:24',  '2010-11-11 21:10:11.123456', '2016-01-01 12:00:00'];
        foreach($goodVars as $var) {
            $this->assertNotEquals('', MySqlUnitemporalDataTable::getTimeStringFromVariable($var), 'Testing ' . print_r($var, true));
        }

    }

    public function testFindRowsWithTime()
    {
        $dataTable = $this->createEmptyDt();
        
        $timeZero = MySqlUnitemporalDataTable::getTimeStringFromVariable('2010-01-01');
        $times = [ '2014-01-01',
            '2015-01-01',
            '2016-01-01'];
       
        $nEntries = 10;
        $theKey = 1000;
        
        // Create different versions of $nEntries
        $ids = [];
        for ($i = 0; $i < $nEntries; $i++) {
            $rowId = $dataTable->createRowWithTime(
                ['somekey' => $theKey],
                $timeZero
            );
            $this->assertNotFalse($rowId);
            $ids[] = $rowId;
            $timesCount = 1;
            foreach ($times as $t) {
                $t = MySqlUnitemporalDataTable::getTimeStringFromVariable($t);
                $r2 = $dataTable->realUpdateRowWithTime(['id' => $rowId,
                    'someotherkey' => 'Value' .
                    $timesCount++], $t);
                $this->assertNotFalse($r2);
            }
        }
        
        
        // Check latest versions
        foreach ($ids as $rowId) {
            $row = $dataTable->getRow($rowId);
            $this->assertNotFalse($row);
            $this->assertEquals($theKey, $row['somekey']);
            $this->assertEquals('Value3', $row['someotherkey']);
        }
        
        // Only the last versions should show up in these searches
        $foundsRows1 = $dataTable->findRows(['someotherkey' => 'Value1']);
        $this->assertEquals([], $foundsRows1);
        
        $foundsRows2 = $dataTable->findRows(['someotherkey' => 'Value2']);
        $this->assertEquals([], $foundsRows2);
        
        $foundsRows3 = $dataTable->findRows(['someotherkey' => 'Value3']);
        $this->assertCount($nEntries, $foundsRows3);
        
        // Time info should be irrelevant for the search:
        $foundsRows3 = $dataTable->findRows(['valid_from'=> $timeZero,
            'someotherkey' => 'Value3']);
        $this->assertCount($nEntries, $foundsRows3);
        
        $foundsRows3 = $dataTable->findRows(['valid_until'=> $timeZero,
            'someotherkey' => 'Value3']);
        $this->assertCount($nEntries, $foundsRows3);
        
        $foundsRows3 = $dataTable->findRows(['valid_from'=> $timeZero,
            'valid_until' => $timeZero,
            'someotherkey' => 'Value3']);
        $this->assertCount($nEntries, $foundsRows3);
        
                
        // Search the keys in the times they are valid
        $foundRows4 = $dataTable->findRowsWithTime(
            ['someotherkey' => 'Value3'],
            false,
            '2016-01-01 12:00:00'
        );
        $this->assertCount(10, $foundRows4);
        
        // timestamps should be fine as well
        $foundRows4 = $dataTable->findRowsWithTime(
            ['someotherkey' => 'Value3'],
            false,
            // a day ago
            MySqlUnitemporalDataTable::getTimeStringFromVariable(time()-86400)
        );
        $this->assertCount(10, $foundRows4);
        
        $foundRows5 = $dataTable->findRowsWithTime(
            ['someotherkey' => 'Value2'],
            false,
            '2015-01-01 12:00:00'
        );
        $this->assertCount(10, $foundRows5);
        
        $foundRows6 = $dataTable->findRowsWithTime(
            ['someotherkey' => 'Value1'],
            false,
            '2014-01-01 12:00:00'
        );
        $this->assertCount(10, $foundRows6);
        
        // Search the common key, only the latest version should
        // be returned
        $foundRows7 = $dataTable->findRows(['somekey' => $theKey]);
        $this->assertCount(10, $foundRows7);
        foreach ($foundRows7 as $row) {
            $this->assertEquals('Value3', $row['someotherkey']);
        }
        
        // Search the common key at other times
        $foundRows8 = $dataTable->findRowsWithTime(
            ['somekey' => $theKey],
            false,
            '2015-01-01 12:00:00'
        );
        $this->assertCount(10, $foundRows8);
        foreach ($foundRows8 as $row) {
            $this->assertEquals('Value2', $row['someotherkey']);
        }
        
        $foundRows9 = $dataTable->findRowsWithTime(
            ['somekey' => $theKey],
            false,
            '2014-01-01 12:00:00'
        );
        $this->assertCount(10, $foundRows9);
        foreach ($foundRows9 as $row) {
            $this->assertEquals('Value1', $row['someotherkey']);
        }
        
        $foundRows10 = $dataTable->findRowsWithTime(
            ['somekey' => $theKey],
            false,
            '2013-01-01'
        );
        $this->assertCount(10, $foundRows10);
        foreach ($foundRows10 as $row) {
            $this->assertTrue(is_null($row['someotherkey']));
        }
        
        $foundRows11 = $dataTable->findRowsWithTime(
            ['somekey' => $theKey],
            false,
            '2000-01-01 12:00:00'
        );
        $this->assertCount(0, $foundRows11);
    }
    
    public function testCreateRowWithTime()
    {
        $dataTable = $this->createEmptyDt();
        $time = MySqlUnitemporalDataTable::now();

        $exceptionCaught = false;
        // Id not integer
        try {
            $res = $dataTable->createRowWithTime(
                ['id' => 'notanumber','value' => 'test'],
                $time
            );
        } catch (\InvalidArgumentException $e) {
            $exceptionCaught = true;
        }

        $this->assertTrue($exceptionCaught);
        
        $res = $dataTable->createRowWithTime(
            ['id' => 1, 'value' => 'test'],
            $time
        );
        $this->assertEquals(1, $res);
        // Trying to create an existing row

        $exceptionCaught = false;
        try {
            $res = $dataTable->createRowWithTime(['id' => 1,
                'value' => 'anothervalue'], $time);
        } catch(\RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $row = $dataTable->getRow(1);
        $this->assertEquals('test', $row['value']);
    }
    
    public function testDeleteRowWithTime()
    {
        $dataTable = $this->createEmptyDt();
        
        $newId = $dataTable->createRow(['value' => 'test']);
        $this->assertNotFalse($newId);
        $time = MySqlUnitemporalDataTable::now();
        
        $result = $dataTable->deleteRowWithTime($newId, $time);
        $this->assertEquals($newId, $result);
        
    }

}
