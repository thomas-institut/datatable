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
use \PDOException;

/**
 * Implements a MySql data table that keeps different versions
 * of its rows. The term 'unitemporal' is taken from
 * Johnston and Weis, 'Managing Time in Relational Databases", 2010, but
 * this this implementation does not necessarily follow the techniques
 * described in that book.
 *
 * The normal DataTable methods for creating, updating and deleting
 * rows do not delete any previous data but just mark that data as
 * not valid any more. Data retrieval methods (getRow and findRows) get
 * the latest versions of the data and strip out the time information, so,
 * if used with the normal methods the class behaves as any other DataTable.
 * There are, however, new methods to retrieve data at previous points in time.
 *
 * The actual MySql table should have and integer id and two datetime
 * columns with precision up to the microsecond:
 *   id INT NOT NULL
 *   valid_from DATETIME(6) NOT NULL
 *   valid_until DATETIME(6) NOT NULL
 *
 * The id column cannot be a primary key because it is not unique. The
 * primary key should be (id, valid_from, valid_until)
 *
 * The class should work for any sistem that implements microtime(),
 * see http://php.net/manual/en/function.microtime.php
 *
 * @author Rafael Nájera <rafael@najera.ca>
 */
class MySqlUnitemporalDataTable extends MySqlDataTable
{
    
    // Error codes
    const MYSQLUNITEMPORALDATATABLE_INVALID_TIME = 2010;
    
    
    // Other constants
    const END_OF_TIMES = '9999-12-31 23:59:59.999999';
    const MYSQL_DATE_FORMAT  = 'Y-m-d H:i:s';
    
    /**
     *
     * @param \PDO $theDb  initialized PDO connection
     * @param string $tableName  SQL table name
     */
    public function __construct($theDb, $tableName)
    {
        
        parent::__construct($theDb, $tableName);
        
        // Override rowExistsById statement
        $this->statements['rowExistsById'] =
                $this->dbConn->prepare('SELECT id FROM ' . $this->tableName .
                        ' WHERE id= :id AND valid_until=' .
                        $this->quote(self::END_OF_TIMES));
    }
    
    
    /**
     * Checks the actual MySql table's data schema
     *
     * This function will be called by MySqlData at construction time
     *
     */
    protected function realIsDbTableValid()
    {
        if (parent::realIsDbTableValid() === false) {
            return false;
        }
        
        if (!$this->isMySqlTableColumnValid('valid_from', 'datetime')) {
            return false;
        }
        
       if (!$this->isMySqlTableColumnValid('valid_until', 'datetime')) {
            return false;
        }
       
        return true;
    }
    
    /**
     * Creates a row valid from the current time.
     *
     * @param type $theRow
     * @return int
     */
    public function realCreateRow($theRow)
    {
        return $this->realCreateRowWithTime($theRow, self::now());
    }
    
    /**
     * Creates a row valid from the given time
     *
     * @param type $theRow
     * @param string $time  in MySql format, e.g., '2010-09-20 18:25:25'
     * @return boolean
     */
    public function createRowWithTime($theRow, $time)
    {
        
        $this->resetError();
        $preparedRow = $this->prepareRowForRealCreation($theRow);
        if ($preparedRow === false) {
            return false;
        }
        return $this->realCreateRowWithTime($preparedRow, $time);
    }
    
    /**
     * Actual creation of a row
     *
     * @param type $theRow
     * @param mixed $timeVar if int, a timestamp; if string, a formatted date
     * @return boolean
     */
    protected function realCreateRowWithTime($theRow, $timeVar)
    {
        if (!$this->isDbTableValid()) {
            return false;
        }
        $time = self::getMySqlDateTimeFromVariable($timeVar);
        if ($time === false) {
            $this->setErrorCode(self::MYSQLUNITEMPORALDATATABLE_INVALID_TIME);
            $this->setErrorMessage('Invalid time given for row creation: ' . print_r($timeVar, true));
            return false;
        }
        
        $theRow['valid_from'] = $time;
        $theRow['valid_until'] = self::END_OF_TIMES;
        
        return parent::realCreateRow($theRow);
    }

    /**
     * Makes a row invalid from the given time
     *
     * @param type $theRow
     * @param string $time
     */
    protected function makeRowInvalid($theRow, string $time)
    {
        $sql = 'UPDATE ' . $this->tableName . ' SET ' .
                ' valid_until=' . $this->quote($time).
                ' WHERE id=' . $theRow['id'] .
                ' AND valid_from = ' . $this->quote($theRow['valid_from']) .
                ' AND valid_until= ' . $this->quote($theRow['valid_until']);
        
        $r = $this->doQuery($sql, 'makeRowInvalid');
        if ($r === false) {
            return false; //@codeCoverageIgnore
        }

        return $theRow['id'];
    }
    
    /**
     * Updates a row keeping the current version
     * @param type $theRow
     * @return type
     */
    public function realUpdateRow($theRow)
    {
        return $this->realUpdateRowWithTime($theRow, self::now());
    }
    
    /**
     * Updates a row at the given time
     * @param type $theRow
     * @param type $timeVar
     * @return type
     */
    public function realUpdateRowWithTime($theRow, $timeVar)
    {
        $time = self::getMySqlDateTimeFromVariable($timeVar);
        if ($time === false) {
            $this->setErrorCode(self::MYSQLUNITEMPORALDATATABLE_INVALID_TIME);
            $this->setErrorMessage('Invalid time given for row update: ' . print_r($timeVar, true));
            return false;
        }
        $oldRow = $this->realGetRow($theRow['id']);
        $this->makeRowInvalid($oldRow, $time);
        foreach (array_keys($oldRow) as $key) {
            if ($key === 'valid_from' or $key ==='valid_until') {
                continue;
            }
            if (!array_key_exists($key, $theRow)) {
                $theRow[$key] = $oldRow[$key];
            }
        }
        return $this->realCreateRowWithTime($theRow, $time);
    }
    
    /**
     * Returns all rows that are valid at the present moment
     *
     * @return array
     */
    public function getAllRows()
    {
        return $this->getAllRowsWithTime(self::now());
    }
    
    /**
     * Returns all row that are/were valid that the given time
     *
     * @param type $time
     * @return array
     */
    public function getAllRowsWithTime($timeVar)
    {
        $this->resetError();
        
        if (!$this->isDbTableValid()) {
            $this->setErrorCode(self::MYSQLDATATABLE_INVALID_TABLE);
            $this->setErrorMessage('Table was found to be invalid at creation time, aborting getAllRowsWithTime');
            return false;
        }
        
        $time = self::getMySqlDateTimeFromVariable($timeVar);
        if ($time === false) {
            $this->setErrorCode(self::MYSQLUNITEMPORALDATATABLE_INVALID_TIME);
            $this->setErrorMessage('Invalid time given for row getAllRowsWithTime: ' . print_r($timeVar, true));
            return false;
        }
        
        $time = $this->quote($time);
        $sql = 'SELECT * FROM ' . $this->tableName .
                ' WHERE valid_from <= ' . $time .
                ' AND valid_until > ' . $time;
        
        $r = $this->doQuery($sql, 'getAllRowsWithTime');
        if ($r === false) {
            return false; //@codeCoverageIgnore
        }
        return $this->forceIntIds($r->fetchAll(PDO::FETCH_ASSOC));
    }
    
    /**
     * Returns the version of the row with the given ID that is
     * valid at the current time.
     *
     * For compatibility with DataTable, it strips time information
     *
     * @param type $rowId
     * @return array
     */
    public function getRow($rowId)
    {
        $this->resetError();
        return $this->realGetRow($rowId, true);
    }
    
    /**
     * Returns the version of the row with the given ID that is
     * valid at the current time, optionally stripping time
     * information from the resulting array.
     *
     * @param type $rowId
     * @param type $stripTimeInfo
     * @return boolean
     */
    public function realGetRow($rowId, $stripTimeInfo = false)
    {
        $theRow = $this->getRowWithTime($rowId, self::now());
        if ($theRow === false) {
            // the error should have beens set in getRowWithTime
            return false;
        }
        if ($stripTimeInfo) {
            unset($theRow['valid_from']);
            unset($theRow['valid_until']);
        }
        return $theRow;
    }
    
    /**
     * Gets the version of the row with the given ID that is/was
     * valid at the given time.
     *
     * The resulting array will include time information
     *
     * @param type $rowId
     * @param type $time
     * @return array|boolean
     */
    public function getRowWithTime($rowId, $timeVar)
    {
        $this->resetError();
        if (!$this->isDbTableValid()) {
            $this->setErrorCode(self::MYSQLDATATABLE_INVALID_TABLE);
            $this->setErrorMessage('Table was found to be invalid at creation time, aborting getRowWithTime');
            return false;
        }
        
        $time = self::getMySqlDateTimeFromVariable($timeVar);
        if ($time === false) {
            $this->setErrorCode(self::MYSQLUNITEMPORALDATATABLE_INVALID_TIME);
            $this->setErrorMessage('Invalid time given for row getAllRowsWithTime: ' . print_r($timeVar, true));
            return false;
        }
      
        $time = $this->quote($time);
        
        $sql = 'SELECT * FROM ' . $this->tableName .
                        ' WHERE `id`=' . $rowId .
                        ' AND `valid_from`<=' . $time .
                        ' AND `valid_until`>' . $time .
                        ' LIMIT 1';
        
        $r = $this->doQuery($sql, 'getRowWithTime');
        if ($r === false) {
            return false; // @codeCoverageIgnore
        }
        $res = $r->fetch(PDO::FETCH_ASSOC);
        if ($res === false) {
            $this->setErrorMessage('The row with id ' . $rowId . ' does not exist');
            $this->setErrorCode(self::DATATABLE_ROW_DOES_NOT_EXIST);
            return false;
        }
        $res['id'] = (int) $res['id'];
        return $res;
    }
    
    /**
     * Finds rows that are valid at the current moment.
     * The rows in the resulting array will contain time information.
     *
     * @param type $theRow
     * @param type $maxResults
     * @return type
     */
    public function realfindRows($theRow, $maxResults)
    {
        return $this->realfindRowsWithTime($theRow, $maxResults, self::now());
    }
    
    /**
     * Finds rows that are/were valid at the given time
     *
     * @param array $theRow
     * @param int $maxResults
     * @return int/array if $maxResults == 1, returns a single int, if not,
     *                   returns an array of ints. Returns false if not
     *                   rows are found
     */
    public function realfindRowsWithTime($theRow, $maxResults, $timeVar)
    {
        $this->resetError();
        if (!$this->isDbTableValid()) {
            $this->setErrorCode(self::MYSQLDATATABLE_INVALID_TABLE);
            $this->setErrorMessage('Table was found to be invalid at creation time, aborting getRowWithTime');
            return false;
        }
        
        $time = self::getMySqlDateTimeFromVariable($timeVar);
        if ($time === false) {
            $this->setErrorCode(self::MYSQLUNITEMPORALDATATABLE_INVALID_TIME);
            $this->setErrorMessage('Invalid time given for row getAllRowsWithTime: ' . print_r($timeVar, true));
            return false;
        }
        $keys = array_keys($theRow);
        $conditions = [];
        foreach ($keys as $key) {
            if ($key === 'valid_from' or $key ==='valid_until') {
                // Ignore time info keys
                continue;
            }
            $c = $key . '=';
            if (is_string($theRow[$key])) {
                $c .= $this->dbConn->quote($theRow[$key]);
            } else {
                $c .= $theRow[$key];
            }
            $conditions[] = $c;
        }
        $time = $this->quote($time);
        $sql = 'SELECT * FROM ' . $this->tableName . ' WHERE ' .
                implode(' AND ', $conditions) .
                ' AND valid_from <= ' . $time .
                ' AND valid_until > ' . $time;
        if ($maxResults) {
            $sql .= ' LIMIT ' . $maxResults;
        }
        
//        $r = $this->doQuery($sql, 'realFindRowsWithTime');
//        if ($r === false) {
//            return false;
//        }
        
        try {
            $r = $this->dbConn->query($sql);
        } catch (PDOException $e) {
            if ( $e->getCode() === '42000') {
                // The exception was thrown because of an SQL syntax error but
                // this should only happen when one of the keys does not exist or
                // is of the wrong type. This just means that the search
                // did not have any results, so let's set the error code
                // to be 'empty result set'
                $this->setErrorCode(self::DATATABLE_EMPTY_RESULT_SET);
                // However, just in case this may be hiding something else, 
                // let's report everything in the error message
                $this->setErrorMessage('Query error in realFindRowsWithTime (reported as no results) : ' . 
                        $e->getMessage() . ' :: query = ' . $sql);
                return false;
            }
            // @codeCoverageIgnoreStart
            $this->setErrorCode(self::MYSQLDATATABLE_QUERY_ERROR);
            $this->setErrorMessage('Query error in realFindRowsWithTime: ' . $e->getMessage() . ' :: query = ' . $sql);
            return false;
            // @codeCoverageIgnoreEnd
        }
        if ($r === false) {
            // @codeCoverageIgnoreStart
            $this->setErrorCode(self::DATATABLE_UNKNOWN_ERROR);
            $this->setErrorMessage('Unknown error in realFindRowsWithTime when executing query: ' . $sql);
            return false;
            // @codeCoverageIgnoreEnd
        }
        
        
        return $this->forceIntIds($r->fetchAll(PDO::FETCH_ASSOC));
    }
    
    public function deleteRowWithTime($rowId, $time)
    {
        $oldRow = $this->realGetRow($rowId);
        return $this->makeRowInvalid($oldRow, $time) !== false ;
    }
    
    /**
     * 'Deletes' a row by making its current version invalid
     * as of the current moment
     *
     * @param type $rowId
     * @return type
     */
    public function realDeleteRow($rowId)
    {
        $oldRow = $this->realGetRow($rowId);
        return $this->makeRowInvalid($oldRow, self::now()) !== false ;
    }
    
    /**
     * Returns the current time in MySQL format with microsecond precision
     *
     * @return string
     */
    public static function now()
    {
        return self::getMySqlDatetimeFromTimestamp(microtime(true));
    }
    
    private static function getMySqlDatetimeFromTimestamp($timeStamp)
    {
        $intTime =  floor($timeStamp);
        $date=date(self::MYSQL_DATE_FORMAT, $intTime);
        $microSeconds = (int) floor(($timeStamp - $intTime)*1000000);
        return sprintf("%s.%06d", $date, $microSeconds);
    }
    
    public static function getMySqlDateTimeFromVariable($timeVar)
    {
        if (is_numeric($timeVar)) {
            return self::getMySqlDatetimeFromTimestamp((float) $timeVar);
        }
        if (is_string($timeVar)) {
            // TODO: actually check that this is a valid time!!!
            return $timeVar;
        }
        return false;
    }
}
