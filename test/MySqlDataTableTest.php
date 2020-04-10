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

namespace ThomasInstitut\DataTable;

require '../vendor/autoload.php';
require_once 'config.php';
require_once 'DataTableTest.php';


use InvalidArgumentException;
use \PDO;
use RuntimeException;

/**
 * Description of SQLDataTableTest
 *
 * @author Rafael Nájera <rafael.najera@uni-koeln.de>
 */
class MySqlDataTableTest extends DataTableTest
{
    
    public $numRows = 100;
    
    const TABLE_NAME  = 'testtable';
    const INTCOLUMN = 'somekey';
    const STRINGCOLUMN = 'someotherkey';
    const OTHERSTRINGCOLUMN = 'value';
    
    public function createEmptyDt() : GenericDataTable
    {
        $pdo = $this->getPdo();
        $this->resetTestDb($pdo);

        $dt = new MySqlDataTable($pdo, self::TABLE_NAME);
        $dt->setLogger($this->getLogger()->withName('MySqlDataTable (' . self::TABLE_NAME . ')'));
        return $dt;

    }
    
    public function getRestrictedDt() : MySqlDataTable
    {
        $restrictedPdo = $this->getRestrictedPdo();
        return new MySqlDataTable($restrictedPdo, self::TABLE_NAME);
    }
    
    public function getPdo() : PDO
    {
        global $config;
        
        return new PDO(
            'mysql:dbname=' . $config['db'] . ';host=' . $config['host'],
            $config['user'],
            $config['pwd']
        );
    }
    
    public function getRestrictedPdo() : PDO
    {
        global $config;
        
        return new PDO(
            'mysql:dbname=' . $config['db'] . ';host=' . $config['host'],
            $config['restricteduser'],
            $config['restricteduserpwd']
        );
    }

    public function resetTestDb(PDO $pdo)
    {

        $idCol = GenericDataTable::COLUMN_ID;
        $intCol = self::INTCOLUMN;
        $stringCol = self::STRINGCOLUMN;
        $otherStringCol = self::OTHERSTRINGCOLUMN;

        $tableSetupSQL =<<<EOD
            DROP TABLE IF EXISTS `testtable`;
            CREATE TABLE IF NOT EXISTS `testtable` (
              `$idCol` int(11) UNSIGNED NOT NULL,
              `$intCol` int(11) DEFAULT NULL,
              `$stringCol` varchar(100) DEFAULT NULL,
              `$otherStringCol` varchar(100) DEFAULT NULL,
              PRIMARY KEY (`$idCol`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
EOD;
        $pdo->query($tableSetupSQL);
    }
    
    public function resetTestDbWithBadTables(PDO $pdo)
    {
        $idCol = GenericDataTable::COLUMN_ID;
        $intCol = self::INTCOLUMN;
        $stringCol = self::STRINGCOLUMN;


        $tableSetupSQL =<<<EOD
            DROP TABLE IF EXISTS `testtablebad1`;
            CREATE TABLE IF NOT EXISTS `testtablebad1` (
              `$idCol` varchar(100) NOT NULL,
              `$intCol` int(11) DEFAULT NULL,
              `$stringCol` varchar(100) DEFAULT NULL,
              PRIMARY KEY (`$idCol`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            DROP TABLE IF EXISTS `testtablebad2`;                
            CREATE TABLE IF NOT EXISTS `testtablebad2` (
              `$intCol` int(11) DEFAULT NULL,
              `$stringCol` varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
EOD;
        $pdo->query($tableSetupSQL);
    }

    
    public function testRestrictedPdo()
    {
        $dataTable = $this->createEmptyDt();
        $restrictedDataTable = $this->getRestrictedDt();

        $stringCol = self::STRINGCOLUMN;

        $exceptionCaught = false;
        try {
            $restrictedDataTable->createRow([$stringCol => 25]);
        } catch (RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(MySqlDataTable::ERROR_MYSQL_QUERY_ERROR, $restrictedDataTable->getErrorCode());
        
        $rowId = $dataTable->createRow([$stringCol => 25]);
        $this->assertNotFalse($rowId);
        $this->assertEquals(MySqlDataTable::ERROR_NO_ERROR, $dataTable->getErrorCode());

        $exceptionCaught = false;
        try {
            $restrictedDataTable->deleteRow($rowId);
        } catch (RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(MySqlDataTable::ERROR_MYSQL_QUERY_ERROR, $restrictedDataTable->getErrorCode());
        
        
        $rows = $restrictedDataTable->getAllRows();
        $this->assertCount(1, $rows);
        $this->assertEquals($rowId, $rows[0][GenericDataTable::COLUMN_ID]);
        
        $result = $restrictedDataTable->rowExists($rowId);
        $this->assertTrue($result);
        
    }
    
    public function testEscaping()
    {
        parent::testEscaping();
        
        $pdo = $this->getPdo();
        $dataTable = new MySqlDataTable($pdo, self::TABLE_NAME);
        // somekey is supposed to be an integer
        $exceptionCaught = false;
        try {
            $dataTable->createRow([self::INTCOLUMN => 'A string']);
        } catch (RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
    }
   
    public function testBadTables()
    {
        $pdo = $this->getPdo();
        $this->resetTestDbWithBadTables($pdo);
        $exceptionCaught = false;
        $errorCode = -1;
        try {
            $dataTable = new MySqlDataTable($pdo, 'testtablebad1');
        } catch(RuntimeException $exception) {
            $exceptionCaught = true;
            $errorCode = $exception->getCode();
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(MySqlDataTable::ERROR_WRONG_COLUMN_TYPE, $errorCode);


        $exceptionCaught = false;
        $errorCode = -1;
        try {
            $dataTable = new MySqlDataTable($pdo, 'testtablebad2');
        } catch(RuntimeException $exception) {
            $exceptionCaught = true;
            $errorCode = $exception->getCode();
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(MySqlDataTable::ERROR_REQUIRED_COLUMN_NOT_FOUND, $errorCode);


        $exceptionCaught = false;
        $errorCode = -1;
        try {
            $dataTable = new MySqlDataTable($pdo, 'nonexistenttable');
        } catch(RuntimeException $exception) {
            $exceptionCaught = true;
            $errorCode = $exception->getCode();
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(MySqlDataTable::ERROR_TABLE_NOT_FOUND, $errorCode);
    }
    
    public function testUpdateRow()
    {
        parent::testUpdateRow();
        
        $pdo = $this->getPdo();
        $dataTable = new MySqlDataTable($pdo, self::TABLE_NAME);
        
        // INTCOLUMN should be an int
        $exceptionCaught = false;
        try {
            $dataTable->updateRow([GenericDataTable::COLUMN_ID => 1, self::INTCOLUMN => 'bad']);
        } catch (RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(MySqlDataTable::ERROR_MYSQL_QUERY_ERROR,
                $dataTable->getErrorCode());
        $this->assertNotEquals('', $dataTable->getErrorMessage());
        
        // Null values are fine (because the table schema allows them)
        $exceptionCaught = false;
        try {
            $dataTable->updateRow([GenericDataTable::COLUMN_ID => 1,  self::OTHERSTRINGCOLUMN => null]);
        }
        catch (RuntimeException $e) {
            $exceptionCaught = true;
        }
        $this->assertFalse($exceptionCaught);
    }
    
    public function testNonExistentRows() 
    {
        parent::testNonExistentRows();
        
        $dataTable = $this->createEmptyDt();
        
        for ($i = 1; $i < 100; $i++) {
            $exceptionCaught = false;
            try {
                $dataTable->getRow($i);
            } catch (InvalidArgumentException $e) {
                $exceptionCaught = true;
            }
            $this->assertTrue($exceptionCaught);
            $this->assertEquals(MySqlDataTable::ERROR_ROW_DOES_NOT_EXIST,
                $dataTable->getErrorCode());
            $this->assertNotEquals('', $dataTable->getErrorMessage());
        }
    }

    public function testSelect() {

        /**
         * @var MySqlDataTable $dataTable
         */
        $dataTable= $this->createEmptyDt();

        $exceptionCaught = false;
        try {
            $dataTable->select('*','', 0, '', 'testSelect');
        } catch (InvalidArgumentException $e) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $r = $dataTable->select('*', GenericDataTable::COLUMN_ID . '=1', 0, 'id ASC', 'testSelect2');

        $this->assertEquals(0, $r->rowCount());

    }
}
