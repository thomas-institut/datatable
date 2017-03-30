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
}
