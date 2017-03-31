<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace DataTable;

require_once 'MySqlDataTableTest.php';

/**
 * Description of MySqlUnitemporalDataTableTest
 *
 * @author Rafael Nájera <rafael.najera@uni-koeln.de>
 */
class MySqlUnitemporalDataTableTest extends MySqlDataTableTest {
    
     public function resetTestDb($pdo)
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
EOD;
        $pdo->query($tableSetupSQL);
        
    }
    
    public function resetTestDbWithBadTables($pdo)
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
    
     public function createEmptyDt() 
    {
        $pdo = $this->getPdo();
        $this->resetTestDb($pdo);
        return new MySqlUnitemporalDataTable($pdo, 'testtable');
    }
    
    public function testBadTables() 
    {
        $pdo = $this->getPdo();
        $this->resetTestDbWithBadTables($pdo);
        $dt = new MySqlUnitemporalDataTable($pdo, 'testtablebad1');

        $this->assertFalse($dt->isDbTableValid());
        
        $dt = new MySqlUnitemporalDataTable($pdo, 'testtablebad2');
        $this->assertFalse($dt->isDbTableValid());
        
        $dt = new MySqlUnitemporalDataTable($pdo, 'testtablebad3');
        $this->assertFalse($dt->isDbTableValid());
        
        $dt = new MySqlUnitemporalDataTable($pdo, 'testtablebad4');
        $this->assertFalse($dt->isDbTableValid());
        
        $dt = new MySqlUnitemporalDataTable($pdo, 'testtablebad5');
        $this->assertFalse($dt->isDbTableValid());
        
        $dt = new MySqlUnitemporalDataTable($pdo, 'testtablebad6');
        $this->assertFalse($dt->isDbTableValid());
        
        $dt = new MySqlUnitemporalDataTable($pdo, 'nonexistenttable');
        $this->assertFalse($dt->isDbTableValid());
        
        // This should all return false right away
        $this->assertFalse($dt->rowExistsById(1));
        $this->assertFalse($dt->createRow(['id' => 1, 'somekey' => 'test']));
        $this->assertFalse($dt->getAllRows());
        $this->assertFalse($dt->getRow(1));
        $this->assertFalse($dt->getMaxId());
        $this->assertFalse($dt->findRows(['id' => 1, 'somekey' => 'test2']));
        
    }
    

    public function testFindRowsWithTime()
    {
        $dt = $this->createEmptyDt();
        
        $t0 = '2010-01-01';
        $times = [ '2014-01-01', 
            '2015-01-01', 
            '2016-01-01'];
       
        $nEntries = 10;
        $theKey = 1000;
        
        $ids = [];
        for ($i = 0; $i < $nEntries; $i++) {
            $id = $dt->createRowWithTime(['somekey' => $theKey], $t0);
            $this->assertNotFalse($id);
            $ids[] = $id;
            $j = 1;
            foreach($times as $t) {
                $r2 = $dt->realUpdateRowWithTime(['id' => $id,
                    'someotherkey' => 'Value' . 
                    $j++], $t);
                $this->assertNotFalse($r2);
            }
        }
        
        foreach($ids as $id) {
            $row = $dt->getRow($id);
            $this->assertNotFalse($row);
            $this->assertEquals($theKey, $row['somekey']);
            $this->assertEquals('Value3', $row['someotherkey']);
        }
        
        $foundsRows1 = $dt->findRows(['someotherkey' => 'Value1']);
        $this->assertEquals([], $foundsRows1);
        
        $foundsRows2 = $dt->findRows(['someotherkey' => 'Value2']);
        $this->assertEquals([], $foundsRows2);
        
        $foundsRows3 = $dt->findRows(['someotherkey' => 'Value3']);
        $this->assertCount($nEntries, $foundsRows3);
        
        $foundRows4 = $dt->realfindRowsWithTime(['someotherkey' => 'Value3'], 
                false, '2016-01-01 12:00:00');
        $this->assertCount(10, $foundRows4);
        
        $foundRows5 = $dt->realfindRowsWithTime(['someotherkey' => 'Value2'], 
                false, '2015-01-01 12:00:00');
        $this->assertCount(10, $foundRows5);
        
        $foundRows6 = $dt->realfindRowsWithTime(['someotherkey' => 'Value1'], 
                false, '2014-01-01 12:00:00');
        $this->assertCount(10, $foundRows6);
        
        $foundRows7 = $dt->findRows(['somekey' => $theKey]);
        $this->assertCount(10, $foundRows7);
        
        $foundRows8 = $dt->realfindRowsWithTime(['somekey' => $theKey], 
                false, '2015-01-01 12:00:00');
        $this->assertCount(10, $foundRows8);
        foreach($foundRows8 as $row) {
            $this->assertEquals('Value2', $row['someotherkey']);
        }
        
        $foundRows9 = $dt->realfindRowsWithTime(['somekey' => $theKey], 
                false, '2014-01-01 12:00:00');
        $this->assertCount(10, $foundRows9);
        foreach($foundRows9 as $row) {
            $this->assertEquals('Value1', $row['someotherkey']);
        }
        
        $foundRows10 = $dt->realfindRowsWithTime(['somekey' => $theKey], 
                false, '2013-01-01');
        $this->assertCount(10, $foundRows10);
        foreach($foundRows10 as $row) {
            $this->assertTrue(is_null($row['someotherkey']));
        }
        
        $foundRows11 = $dt->realfindRowsWithTime(['somekey' => $theKey], 
                false, '2000-01-01 12:00:00');
        $this->assertCount(0, $foundRows11);
        
    }
    
    public function testCreateRowWithTime()
    {
        $dt = $this->createEmptyDt();
        $time = MySqlUnitemporalDataTable::now();
        // Id not integer
        $r = $dt->createRowWithTime(['id' => 'notanumber', 'value' => 'test'], 
                $time);
        $this->assertFalse($r);
        
        
        $r = $dt->createRowWithTime(['id' => 1, 'value' => 'test'], $time);
        $this->assertEquals(1, $r);
        // Trying to create an existing row
        $r = $dt->createRowWithTime(['id' => 1, 'value' => 'anothervalue'], $time);
        $this->assertFalse($r);
        $row = $dt->getRow(1);
        $this->assertEquals('test', $row['value']);
    }
}
