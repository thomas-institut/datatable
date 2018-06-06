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

require '../vendor/autoload.php';
require_once 'config.php';
require_once 'DataTableTest.php';


use \PDO;

/**
 * Description of SQLDataTableTest
 *
 * @author Rafael Nájera <rafael.najera@uni-koeln.de>
 */
class MySqlDataTableTest extends DataTableTest
{
    
    public $numRows = 100;
    
    const TABLE_NAME  = 'testtable';
    
    public function createEmptyDt()
    {
        $pdo = $this->getPdo();
        $this->resetTestDb($pdo);
        return new MySqlDataTable($pdo, self::TABLE_NAME);
    }
    
    public function getRestrictedDt()
    {
        $restrictedPdo = $this->getRestrictedPdo();
        return new MySqlDataTable($restrictedPdo, self::TABLE_NAME);
    }
    
    public function getPdo()
    {
        global $config;
        
        return new PDO(
            'mysql:dbname=' . $config['db'] . ';host=' . $config['host'],
            $config['user'],
            $config['pwd']
        );
    }
    
    public function getRestrictedPdo()
    {
        global $config;
        
        return new PDO(
            'mysql:dbname=' . $config['db'] . ';host=' . $config['host'],
            $config['restricteduser'],
            $config['restricteduserpwd']
        );
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
    
    public function testBadPdo()
    {
        $dt = new MySqlDataTable(false, 'somename');
        $this->assertEquals(MySqlDataTable::MYSQLDATATABLE_INVALID_DB_CONNECTION, $dt->getErrorCode());
    }
    
    public function testRestrictedPdo()
    {
        $dataTable = $this->createEmptyDt();
        $restrictedDataTable = $this->getRestrictedDt();
        
        $rowId = $restrictedDataTable->createRow(['somekey' => 25]);
        $this->assertFalse($rowId);
        $this->assertEquals(MySqlDataTable::MYSQLDATATABLE_QUERY_ERROR, $restrictedDataTable->getErrorCode());
        
        $rowId = $dataTable->createRow(['somekey' => 25]);
        $this->assertNotFalse($rowId);
        $this->assertEquals(MySqlDataTable::DATATABLE_NOERROR, $dataTable->getErrorCode());
        
        $result = $restrictedDataTable->deleteRow($rowId);
        $this->assertFalse($result);
        $this->assertEquals(MySqlDataTable::MYSQLDATATABLE_QUERY_ERROR, $restrictedDataTable->getErrorCode());
        
        
        $rows = $restrictedDataTable->getAllRows();
        $this->assertCount(1, $rows);
        $this->assertEquals($rowId, $rows[0]['id']);
        
        $result = $restrictedDataTable->rowExistsById($rowId);
        $this->assertTrue($result);
        
    }
    
    public function testEscaping()
    {
        parent::testEscaping();
        
        $pdo = $this->getPdo();
        $dataTable = new MySqlDataTable($pdo, 'testtable');
        // somekey is supposed to be an integer
        $rowId = $dataTable->createRow(['somekey' => 'A string']);
        $this->assertSame(false, $rowId);
    }
   
    public function testBadTables()
    {
        $pdo = $this->getPdo();
        $this->resetTestDbWithBadTables($pdo);
        $dataTable = new MySqlDataTable($pdo, 'testtablebad1');

        $this->assertFalse($dataTable->isDbTableValid());
        $this->assertEquals(MySqlDataTable::MYSQL_DATATABLE_WRONG_COLUMN_TYPE, 
                $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());
        
        $dataTable = new MySqlDataTable($pdo, 'testtablebad2');
        $this->assertFalse($dataTable->isDbTableValid());
        $this->assertEquals(MySqlDataTable::MYSQLDATATABLE_REQUIRED_COLUMN_NOT_FOUND, 
                $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());
        
        $dataTable = new MySqlDataTable($pdo, 'nonexistenttable');
        $this->assertFalse($dataTable->isDbTableValid());
        $this->assertEquals(MySqlDataTable::MYSQLDATATABLE_TABLE_NOT_FOUND, 
                $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());
        
        // This should all return false right away
        $this->assertFalse($dataTable->rowExistsById(1));
        $this->assertEquals(MySqlDataTable::MYSQLDATATABLE_INVALID_TABLE, 
                $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());
        $this->assertFalse($dataTable->createRow(['id' => 1,
            'somekey' => 'test']));
        $this->assertEquals(MySqlDataTable::MYSQLDATATABLE_INVALID_TABLE, 
                $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());
        $this->assertFalse($dataTable->getAllRows());
        $this->assertEquals(MySqlDataTable::MYSQLDATATABLE_INVALID_TABLE, 
                $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());
        $this->assertFalse($dataTable->getRow(1));
        $this->assertEquals(MySqlDataTable::MYSQLDATATABLE_INVALID_TABLE, 
                $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());
        $this->assertFalse($dataTable->getMaxId());
        $this->assertEquals(MySqlDataTable::MYSQLDATATABLE_INVALID_TABLE, 
                $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());
        $this->assertFalse($dataTable->findRows(['id' => 1,
            'somekey' => 'test2']));
        $this->assertEquals(MySqlDataTable::MYSQLDATATABLE_INVALID_TABLE, 
                $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());
    }
    
    public function testUpdateRow()
    {
        parent::testUpdateRow();
        
        $pdo = $this->getPdo();
        $dataTable = new MySqlDataTable($pdo, 'testtable');
        
        // Somekey should be an int
        $this->assertFalse($dataTable->updateRow(['id' => 1,
            'somekey' => 'bad']));
        $this->assertEquals(MySqlDataTable::MYSQLDATATABLE_QUERY_ERROR, 
                $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());
        
        // Null values are fine (because the table schema allows them)
        $this->assertNotFalse($dataTable->updateRow(['id' => 1,
            'value' => null]));
    }
    
    public function testNonExistentRows() 
    {
        parent::testNonExistentRows();
        
        $dataTable = $this->createEmptyDt();
        
        for ($i = 1; $i < 100; $i++) {
            $this->assertFalse($dataTable->getRow($i));
            $this->assertEquals(MySqlDataTable::DATATABLE_ROW_DOES_NOT_EXIST, 
                $dataTable->getErrorCode());
            $this->assertNotEquals('', $dataTable->getErrorMessage());
        }
        
    }
}
