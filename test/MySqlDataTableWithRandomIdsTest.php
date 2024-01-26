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
use PDO;

require '../vendor/autoload.php';

require_once 'MySqlDataTableReferenceTest.php';

/**
 * Description of SQLDataTableTest
 *
 * @author Rafael Nájera <rafael.najera@uni-koeln.de>
 */
class MySqlDataTableWithRandomIdsTest extends MySqlDataTableReferenceTest
{
    
    public int $minId = 100000;
    public int $maxId = 200000;


    protected function constructMySqlDataTable(PDO $pdo) : MySqlDataTable {
        return new MySqlDataTableWithRandomIds($pdo, self::TABLE_NAME,  $this->minId, $this->maxId, self::ID_COLUMN_NAME);
    }

    protected function getLoggerNamePrefix(): string
    {
       return 'MySqlDtWithRandomIds';
    }

    public function getRestrictedDt() : MySqlDataTable
    {
        $restrictedPdo = $this->getRestrictedPdo();
        return new MySqlDataTableWithRandomIds(
            $restrictedPdo,
            self::TABLE_NAME,
            $this->minId,
            $this->maxId,
            self::ID_COLUMN_NAME
        );
    }

    /**
     * @throws RowAlreadyExists
     */
    public function testRandomIds()
    {
        
        $dataTable = $this->getTestDataTable();
       
        // Adding new rows
        $nRows = 10;
        for ($i = 0; $i < $nRows; $i++) {
            $newId = $dataTable->createRow([ self::INT_COLUMN => $i,
                self::STRING_COLUMN => "textvalue$i"]);
            $this->assertGreaterThanOrEqual($this->minId, $newId);
            $this->assertLessThanOrEqual($this->maxId, $newId);
        }
        
        // Add new rows with fixed Ids
        $nRows = $this->numRows;
        for ($i = 0; $i < $nRows; $i++) {
            $newId = $dataTable->createRow([self::ID_COLUMN_NAME => $i+1, self::INT_COLUMN => $i,
                self::STRING_COLUMN => "textvalue$i"]);
            $this->assertEquals($i+1, $newId);
        }
        
        // Trying to add rows with random Ids, but the Ids are all
        // already taken
        $nRows = 10;
        $pdo = $this->getPdo();
        $dt2 = new MySqlDataTableWithRandomIds(
            $pdo,
            self::TABLE_NAME,
            1,
            $this->numRows, self::ID_COLUMN_NAME
        );
        for ($i = 0; $i < $nRows; $i++) {
            $newID = $dt2->createRow([ self::INT_COLUMN => $i,
                self::STRING_COLUMN => "textvalue$i"]);
            $this->assertNotSame(false, $newID);
            $this->assertGreaterThan($this->numRows, $newID);
        }
    }

}
