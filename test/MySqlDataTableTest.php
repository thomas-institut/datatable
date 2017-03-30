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

require_once 'DataTableTest.php';

use PHPUnit\Framework\TestCase;
use \PDO;
/**
 * Description of SQLDataTableTest
 *
 * @author Rafael Nájera <rafael.najera@uni-koeln.de>
 */
class MySqlDataTableTest extends DataTableTest {
    
    var $numRows = 100;
    
    public function createEmptyDt() 
    {
        $pdo = $this->getPdo();
        $this->resetTestDb($pdo);
        return new MySqlDataTable($pdo, 'testtable');
    }
    
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
              `value` varchar(100) DEFAULT NULL,
              PRIMARY KEY (`id`)
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
EOD;
        $pdo->query($tableSetupSQL);
        
    }
    
    function testEscaping()
    {
        parent::testEscaping();
        
        $pdo = $this->getPdo();
        $dt = new MySqlDataTable($pdo, 'testtable');
        // somekey is supposed to be an integer
        $id = $dt->createRow(['somekey' => 'A string']);
        $this->assertSame(false, $id);
    }

   
    public function testBadTables() 
    {
        $pdo = $this->getPdo();
        $this->resetTestDbWithBadTables($pdo);
        $dt = new MySqlDataTable($pdo, 'testtablebad1');

        $this->assertFalse($dt->isDbTableValid());
        
        $dt = new MySqlDataTable($pdo, 'testtablebad2');
        $this->assertFalse($dt->isDbTableValid());
        
        $dt = new MySqlDataTable($pdo, 'nonexistenttable');
        $this->assertFalse($dt->isDbTableValid());
        
        // This should all return false right away
        $this->assertFalse($dt->rowExistsById(1));
        $this->assertFalse($dt->createRow(['id' => 1, 'somekey' => 'test']));
        $this->assertFalse($dt->getAllRows());
        $this->assertFalse($dt->getRow(1));
        $this->assertFalse($dt->getMaxId());
        $this->assertFalse($dt->findRows(['id' => 1, 'somekey' => 'test2']));
        
    }
    
    public function testUpdateRow() {
        parent::testUpdateRow();
        
        $pdo = $this->getPdo();
        $dt = new MySqlDataTable($pdo, 'testtable');
        
        // Somekey should be an int
        $this->assertFalse($dt->updateRow(['id' => 1, 'somekey' => 'bad']));
        
        // Null values are fine (because the table schema allows them)
        $this->assertNotFalse($dt->updateRow(['id' => 1, 'value' => NULL]));
    }
    

}
