<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace DataTable;

use InvalidArgumentException;
use PDO;
use RuntimeException;

require '../vendor/autoload.php';
require_once 'MySqlDataTableTest.php';

/**
 * Description of MySqlUnitemporalDataTableTest
 *
 * @author Rafael Nájera <rafael.najera@uni-koeln.de>
 */
class MySqlUnitemporalDataTableTest extends MySqlDataTableTest
{
    const INTCOLUMN = 'somekey';
    const STRINGCOLUMN = 'someotherkey';
    const OTHERSTRINGCOLUMN = 'value';

    public function resetTestDb(PDO $pdo)
    {
        $intCol = self::INTCOLUMN;
        $stringCol = self::STRINGCOLUMN;
        $otherStringCol = self::OTHERSTRINGCOLUMN;
        $tableName = self::TABLE_NAME;

        $tableSetupSQL =<<<EOD
            DROP TABLE IF EXISTS `$tableName`;
            CREATE TABLE IF NOT EXISTS `$tableName` (
              `id` int(11) UNSIGNED NOT NULL,
              `valid_from` datetime(6) NOT NULL,
              `valid_until` datetime(6) NOT NULL,
              `$intCol` int(11) DEFAULT NULL,
              `$stringCol` varchar(100) DEFAULT NULL,
              `$otherStringCol` varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            ALTER TABLE `testtable` ADD PRIMARY KEY( `id`, `valid_from`, `valid_until`);
EOD;
        $pdo->query($tableSetupSQL);
    }
    
    public function resetTestDbWithBadTables(PDO $pdo)
    {

        $intCol = self::INTCOLUMN;
        $stringCol = self::STRINGCOLUMN;

        $tableSetupSQL =<<<EOD
            DROP TABLE IF EXISTS `testtablebad1`;
            CREATE TABLE IF NOT EXISTS `testtablebad1` (
              `id` varchar(100) NOT NULL,
              `$intCol` int(11) DEFAULT NULL,
              `$stringCol` varchar(100) DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            DROP TABLE IF EXISTS `testtablebad2`;                
            CREATE TABLE IF NOT EXISTS `testtablebad2` (
              `$intCol` int(11) DEFAULT NULL,
              `$stringCol` varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            DROP TABLE IF EXISTS `testtablebad3`;   
            CREATE TABLE IF NOT EXISTS `testtablebad3` (
              `id` int(11) UNSIGNED NOT NULL,
              `valid_from` int(11) NOT NULL,
              `valid_until` datetime(6) NOT NULL,
              `$intCol` int(11) DEFAULT NULL,
              `$stringCol` varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            DROP TABLE IF EXISTS `testtablebad4`;   
            CREATE TABLE IF NOT EXISTS `testtablebad4` (
              `id` int(11) UNSIGNED NOT NULL,
              `valid_from` datetime(6) NOT NULL,
              `valid_until` int(11) NOT NULL,
              `$intCol` int(11) DEFAULT NULL,
              `$stringCol` varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;    
            DROP TABLE IF EXISTS `testtablebad5`;  
            CREATE TABLE IF NOT EXISTS `testtablebad5` (
              `id` int(11) UNSIGNED NOT NULL,
              `valid_until` datetime(6) NOT NULL,
              `$intCol` int(11) DEFAULT NULL,
              `$stringCol` varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            DROP TABLE IF EXISTS `testtablebad6`;  
            CREATE TABLE IF NOT EXISTS `testtablebad6` (
              `id` int(11) UNSIGNED NOT NULL,
              `valid_from` datetime(6) NOT NULL,
              `$intCol` int(11) DEFAULT NULL,
              `$stringCol` varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;  
EOD;
        $pdo->query($tableSetupSQL);
    }
    
    public function createEmptyDt() : DataTable
    {
        $pdo = $this->getPdo();
        $this->resetTestDb($pdo);
        $dt = new MySqlUnitemporalDataTable($pdo, self::TABLE_NAME);
        $dt->setLogger($this->getLogger()->withName('MySqlUnitemporalDT (' . self::TABLE_NAME . ')'));
        return $dt;
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
            new MySqlUnitemporalDataTable($pdo, 'testtablebad1');
        } catch(RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);


        $exceptionCaught = false;
        try {
            new MySqlUnitemporalDataTable($pdo, 'testtablebad2');
        } catch(RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            new MySqlUnitemporalDataTable($pdo, 'testtablebad3');
        } catch(RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            new MySqlUnitemporalDataTable($pdo, 'testtablebad4');
        } catch(RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            new MySqlUnitemporalDataTable($pdo, 'testtablebad5');
        } catch(RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            new MySqlUnitemporalDataTable($pdo, 'testtablebad6');
        } catch(RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            new MySqlUnitemporalDataTable($pdo, 'nonexistenttable');
        } catch(RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
    }

    public function testGetTimeStringFromVariable() {

        $badVars = [ '', [], '2019-01-01.', '2019-01-01.123123', '2019-23-12', '2019-12-40',
            '2019-01-01 35:00:00', '2019-01-01 12:65:00', '2019-01-01 12:00:65'];

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
        $someInt = 1000;
        $nTimes = count($times);
        
        // Create different versions of $nEntries
        $ids = [];
        for ($i = 0; $i < $nEntries; $i++) {
            $rowId = $dataTable->createRowWithTime(
                [self::INTCOLUMN => $someInt],
                $timeZero
            );
            $ids[] = $rowId;
            $timesCount = 1;
            foreach ($times as $t) {
                $t = MySqlUnitemporalDataTable::getTimeStringFromVariable($t);
                $r2 = $dataTable->realUpdateRowWithTime(['id' => $rowId,
                    self::STRINGCOLUMN => 'Value' .
                    $timesCount++], $t);
                $this->assertNotFalse($r2);
            }
        }
        
        
        // Check latest versions
        foreach ($ids as $rowId) {
            $row = $dataTable->getRow($rowId);
            $this->assertNotFalse($row);
            $this->assertEquals($someInt, $row[self::INTCOLUMN]);
            $this->assertEquals('Value' . $nTimes, $row[self::STRINGCOLUMN]);
        }
        
        // Only the last versions should show up in these searches
        for($i = 1; $i < $nTimes; $i++) {
            $foundsRows = $dataTable->findRows([self::STRINGCOLUMN => 'Value' . $i]);
            $this->assertEquals([], $foundsRows);
        }

        $foundsRows = $dataTable->findRows([self::STRINGCOLUMN => 'Value' . $nTimes]);
        $this->assertCount($nEntries, $foundsRows);
        
        // Time info should be irrelevant for the search:
        $foundsRows3 = $dataTable->findRows(['valid_from'=> $timeZero,
            self::STRINGCOLUMN => 'Value3']);
        $this->assertCount($nEntries, $foundsRows3);
        
        $foundsRows3 = $dataTable->findRows(['valid_until'=> $timeZero,
            self::STRINGCOLUMN => 'Value3']);
        $this->assertCount($nEntries, $foundsRows3);
        
        $foundsRows3 = $dataTable->findRows(['valid_from'=> $timeZero,
            'valid_until' => $timeZero,
            self::STRINGCOLUMN => 'Value3']);
        $this->assertCount($nEntries, $foundsRows3);
        
                
        // Search the keys in the times they are valid
        $foundRows4 = $dataTable->findRowsWithTime(
            [self::STRINGCOLUMN => 'Value3'],
            false,
            '2016-01-01 12:00:00'
        );
        $this->assertCount(10, $foundRows4);
        
        // timestamps should be fine as well
        $foundRows4 = $dataTable->findRowsWithTime(
            [self::STRINGCOLUMN => 'Value3'],
            false,
            // a day ago
            MySqlUnitemporalDataTable::getTimeStringFromVariable(time()-86400)
        );
        $this->assertCount(10, $foundRows4);
        
        $foundRows5 = $dataTable->findRowsWithTime(
            [self::STRINGCOLUMN => 'Value2'],
            false,
            '2015-01-01 12:00:00'
        );
        $this->assertCount(10, $foundRows5);
        
        $foundRows6 = $dataTable->findRowsWithTime(
            [self::STRINGCOLUMN => 'Value1'],
            false,
            '2014-01-01 12:00:00'
        );
        $this->assertCount(10, $foundRows6);
        
        // Search the common key, only the latest version should
        // be returned
        $foundRows7 = $dataTable->findRows([self::INTCOLUMN => $someInt]);
        $this->assertCount(10, $foundRows7);
        foreach ($foundRows7 as $row) {
            $this->assertEquals('Value3', $row[self::STRINGCOLUMN]);
        }
        
        // Search the common key at other times
        $foundRows8 = $dataTable->findRowsWithTime(
            [self::INTCOLUMN => $someInt],
            false,
            '2015-01-01 12:00:00'
        );
        $this->assertCount(10, $foundRows8);
        foreach ($foundRows8 as $row) {
            $this->assertEquals('Value2', $row[self::STRINGCOLUMN]);
        }
        
        $foundRows9 = $dataTable->findRowsWithTime(
            [self::INTCOLUMN => $someInt],
            false,
            '2014-01-01 12:00:00'
        );
        $this->assertCount(10, $foundRows9);
        foreach ($foundRows9 as $row) {
            $this->assertEquals('Value1', $row[self::STRINGCOLUMN]);
        }
        
        $foundRows10 = $dataTable->findRowsWithTime(
            [self::INTCOLUMN => $someInt],
            false,
            '2013-01-01'
        );
        $this->assertCount(10, $foundRows10);
        foreach ($foundRows10 as $row) {
            $this->assertTrue(is_null($row[self::STRINGCOLUMN]));
        }
        
        $foundRows11 = $dataTable->findRowsWithTime(
            [self::INTCOLUMN => $someInt],
            false,
            '2000-01-01 12:00:00'
        );
        $this->assertCount(0, $foundRows11);
    }
    
    public function testCreateRowWithTime()
    {
        $dataTable = $this->createEmptyDt();
        $time = MySqlUnitemporalDataTable::now();


        // Bad time
        $exceptionCaught = false;
        try{
            $dataTable->createRowWithTime(
                ['id' => 1, self::OTHERSTRINGCOLUMN => 'test'],
                'badtime');
        } catch (InvalidArgumentException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(MySqlUnitemporalDataTable::ERROR_INVALID_TIME, $dataTable->getErrorCode());

        $id1 = $dataTable->createRowWithTime(
            ['id' => 1, self::OTHERSTRINGCOLUMN => 'test'],
            $time
        );
        $this->assertEquals(1, $id1);

        // Id not integer : a new Id must be generated

        $id2 = $dataTable->createRowWithTime(['id' => 'notanumber',self::OTHERSTRINGCOLUMN => 'test2'],$time);
        $this->assertNotEquals($id1, $id2);

        // Trying to create an existing row
        $exceptionCaught = false;
        try {
            $dataTable->createRowWithTime(['id' => 1,
                self::OTHERSTRINGCOLUMN => 'anothervalue'], $time);
        } catch(InvalidArgumentException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $row = $dataTable->getRow($id1);
        $this->assertEquals('test', $row[self::OTHERSTRINGCOLUMN]);
    }
    
    public function testDeleteRowWithTime()
    {
        $dataTable = $this->createEmptyDt();
        
        $newId = $dataTable->createRow([self::OTHERSTRINGCOLUMN => 'test']);
        $this->assertNotFalse($newId);

        // Bad time
        $exceptionCaught = false;
        try{
            $dataTable->deleteRowWithTime($newId, 'badtime');
        } catch (InvalidArgumentException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(MySqlUnitemporalDataTable::ERROR_INVALID_TIME, $dataTable->getErrorCode());


        $time = MySqlUnitemporalDataTable::now();
        
        $result = $dataTable->deleteRowWithTime($newId, $time);
        $this->assertEquals($newId, $result);

    }

    public function testGetAllRowsWithTime() {

        /**
         * @var MySqlUnitemporalDataTable $dataTable
         */
        $dataTable = $this->createEmptyDt();

        $this->assertEquals([], $dataTable->getAllRowsWithTime('2019-01-01'));


    }

    public function testBadTimes() {

        /**
         * @var MySqlUnitemporalDataTable $dataTable
         */
        $dataTable = $this->createEmptyDt();

        // get all rows
        $exceptionCaught = false;
        try {
            $dataTable->getAllRowsWithTime('badtime');
        } catch (InvalidArgumentException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(MySqlUnitemporalDataTable::ERROR_INVALID_TIME, $dataTable->getErrorCode());

        $newId = $dataTable->createRowWithTime([self::INTCOLUMN => 1000], '2010-10-10 10:10:10');

        $this->assertNotEquals(0, $newId);

        // Get row
        $exceptionCaught = false;
        $theRow = [];
        try {
            $theRow = $dataTable->getRowWithTime($newId, 'badtime');
        } catch (InvalidArgumentException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(MySqlUnitemporalDataTable::ERROR_INVALID_TIME, $dataTable->getErrorCode());
        $this->assertEquals([], $theRow);

        // update row
        $exceptionCaught = false;
        try {
            $dataTable->realUpdateRowWithTime([ 'id' => $newId, self::INTCOLUMN => 1001], 'badtime');
        } catch (InvalidArgumentException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(MySqlUnitemporalDataTable::ERROR_INVALID_TIME, $dataTable->getErrorCode());

        $theRow = $dataTable->getRow($newId);
        $this->assertEquals(1000, $theRow[self::INTCOLUMN]);



    }



}
