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

use \PDO;

/**
 * Implements a MySql data table that keeps different versions
 * of its rows.
 * 
 * The normal DataTable methods for creating, updating and deleting
 * rows do not delete any previous data but just marks it as 
 * not valid any more. There are new methods to retrieve data
 * at previous points in time.
 *
 * The actual MySql table should have and integer id and two datetime 
 * columns with precision up to the microsecond:
 *   id INT
 *   valid_from DATETIME(6)
 *   valid_until DATETIME(6)
 * 
 * The class should work for any sistem that implements microtime(),
 * see http://php.net/manual/en/function.microtime.php
 *  
 * @author Rafael Nájera <rafael@najera.ca>
 */
class MySqlUnitemporalDataTable extends MySqlDataTable
{
    
    const END_OF_TIMES = '9999-12-31 23:59:59.999999';
    const MYSQL_DATE_FORMAT  = 'Y-m-d H:i:s';
    
    /**
     * 
     * @param \PDO $theDb  initialized PDO connection
     * @param string $tn  SQL table name
     */
    public function __construct($theDb, $tn)
    {
        
        parent::__construct($theDb, $tn);
        
        // Override rowExistsById statement
        $this->statements['rowExistsById'] = 
                $this->dbConn->prepare('SELECT id FROM ' . $this->tableName . 
                        ' WHERE id= :id AND valid_until=' . 
                        $this->quote(self::END_OF_TIMES));
    }
    
    
    /**
     * Returns true if the table in the DB has 
     * at least an id column of type int
     */
    protected function realIsDbTableValid()
    {
        if (parent::realIsDbTableValid() === false) {
            return false;
        }
        // Check valid_from column
        $result = $this->dbConn->query(
                'SHOW COLUMNS FROM ' . $this->tableName . ' LIKE \'valid_from\'');
        if ($result->rowCount() != 1) {
            return false;
        }
        $columnInfo = $result->fetch(PDO::FETCH_ASSOC);
        if (!preg_match('/^datetime/', $columnInfo['Type'])) {
            return false;
        }
        // Check valid_until column
        $result2 = $this->dbConn->query(
                'SHOW COLUMNS FROM ' . $this->tableName . ' LIKE \'valid_until\'');
        if ($result2->rowCount() != 1) {
            return false;
        }
        $columnInfo2 = $result2->fetch(PDO::FETCH_ASSOC);
        if (!preg_match('/^datetime/', $columnInfo2['Type'])) {
            return false;
        }
        return true;
    }
    
    public function realCreateRow($theRow) {
        return $this->realCreateRowWithTime($theRow, self::now());
    }
    
    public function createRowWithTime($theRow, $time){
        if (!isset($theRow['id']) || $theRow['id']===0){
            $theRow['id'] = $this->getOneUnusedId();
            if ($theRow['id'] === false){
                return false;
            }
        }
        else {
            if (!is_int($theRow['id'])){
                return false;
            }
            if ($this->rowExistsById($theRow['id'])){
                return false;
            }
        }
        return $this->realCreateRowWithTime($theRow, $time);
    }
    
    public function realCreateRowWithTime($theRow, $time)
    {
        if (!$this->isDbTableValid()) {
            return false;
        }
        $keys = array_keys($theRow);
        $theRow['valid_from'] = $time;
        $theRow['valid_until'] = self::END_OF_TIMES;
        
        //print "Ready to real create row now\n";
        //var_dump($theRow);
        
        return parent::realCreateRow($theRow);
    }

    /**
     * 
     * @param type $theRow
     * @param type $time
     */
    public function makeRowInvalid($theRow, $time)
    {
        $sql = 'UPDATE ' . $this->tableName . ' SET ' . 
                ' valid_until=' . $this->quote($time). 
                ' WHERE id=' . $theRow['id'] .
                ' AND valid_from = ' . $this->quote($theRow['valid_from']) . 
                ' AND valid_until= ' . $this->quote($theRow['valid_until']);
        if ($this->dbConn->query($sql) === false) {
            // Can't get here in testing: query only fails on MySQL failure
            return false; // @codeCoverageIgnore  
        }
        return $theRow['id'];
    }
    
    public function realUpdateRow($theRow) {
        return $this->realUpdateRowWithTime($theRow, self::now());
    }
    
    public function realUpdateRowWithTime($theRow, $time)
    {
        $oldRow = $this->realGetRow($theRow['id']);
        $this->makeRowInvalid($oldRow, $time);
        foreach(array_keys($oldRow) as $key) {
            if ($key === 'valid_from' or $key==='valid_until') {
                continue;
            }
            if (!array_key_exists($key, $theRow)) {
                $theRow[$key] = $oldRow[$key];
            }   
        }
        return $this->realCreateRowWithTime($theRow, $time);
    }
    
    public function getAllRows() 
    {
        return $this->getAllRowsWithTime(self::now());
    }
    
    public function getAllRowsWithTime($time) 
    {
        if (!$this->isDbTableValid()) {
            return false;
        }
        $time = $this->quote($time);
        $r = $this->dbConn->query('SELECT * FROM ' . $this->tableName . 
                ' WHERE valid_from <= ' . $time . 
                ' AND valid_until > ' . $time);
        if ($r === false) {
            // Can't get here in testing: query only fails on MySQL failure
            return false; // @codeCoverageIgnore  
        }
        return $this->forceIntIds($r->fetchAll(PDO::FETCH_ASSOC));
    }
    
    
    public function getRow($rowId)
    {
        return $this->realGetRow($rowId, true);
    }
    
    public function realGetRow($rowId, $stripTimeInfo=false)
    {
        $theRow = $this->getRowWithTime($rowId, self::now());
        if ($theRow === false) {
            return false;
        }
        if ($stripTimeInfo) {
            unset($theRow['valid_from']);
            unset($theRow['valid_until']);
        }
        return $theRow;
    }
    
    public function getRowWithTime($rowId, $time)
    {
        
        if (!$this->isDbTableValid()) {
            return false;
        }
      
        $time = $this->quote($time);
        $query = 'SELECT * FROM ' . $this->tableName . 
                        ' WHERE `id`=' . $rowId . 
                        ' AND `valid_from`<=' . $time . 
                        ' AND `valid_until`>' . $time . 
                        ' LIMIT 1';
        $r = $this->dbConn
                ->query($query)
                ->fetch(PDO::FETCH_ASSOC);
        
        if ($r === false) {
            // Can't get here in testing: query only fails on MySQL failure
            return false; // @codeCoverageIgnore  
        }
        $r['id'] = (int) $r['id'];
        return $r;
        
    }
    
    public function realfindRows($theRow, $maxResults)
    {
        return $this->realfindRowsWithTime($theRow, $maxResults, self::now());
    }
    
    /**
     * 
     * @param array $theRow
     * @param int $maxResults  
     * @return int/array if $maxResults == 1, returns a single int, if not, 
     *                   returns an array of ints. Returns false if not
     *                   rows are found
     */
    public function realfindRowsWithTime($theRow, $maxResults, $time)
    {
        if (!$this->isDbTableValid()) {
            return false;
        }
        $keys = array_keys($theRow);
        $conditions = [];
        foreach ($keys as $key){
            $c = $key . '=';
            if (is_string($theRow[$key])) {
                $c .= $this->dbConn->quote($theRow[$key]);
            } 
            else {
                $c .= $theRow[$key];
            }
            $conditions[] = $c;
        }
        $time = $this->quote($time);
        $sql = 'SELECT * FROM ' . $this->tableName . ' WHERE ' . 
                implode(' AND ', $conditions) . 
                ' AND valid_from <= ' . $time . 
                ' AND valid_until > ' . $time;
        if ($maxResults){
            $sql .= ' LIMIT ' . $maxResults;
        }
        $r = $this->dbConn->query($sql);
        if ( $r === false) {
            return false;
        }

        return $this->forceIntIds($r->fetchAll(PDO::FETCH_ASSOC));
    }
    
    
    public function realDeleteRow($rowId)
    {
        $oldRow = $this->realGetRow($rowId);
        return $this->makeRowInvalid($oldRow, self::now()) !== false ;
    }
    
    public static function now()
    {
        $timeNow = microtime(true);
        $intTime =  floor($timeNow);
        $date=date(self::MYSQL_DATE_FORMAT, $intTime);
        $microSeconds = (int) floor(($timeNow - $intTime)*1000000);
        return sprintf("%s.%06d", $date, $microSeconds);
    }
}
