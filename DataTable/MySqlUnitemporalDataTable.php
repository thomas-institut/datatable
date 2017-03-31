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
        // Check valid_from column
        $result = $this->dbConn->query(
            'SHOW COLUMNS FROM ' . $this->tableName . ' LIKE \'valid_from\''
        );
        if ($result->rowCount() != 1) {
            return false;
        }
        $columnInfo = $result->fetch(PDO::FETCH_ASSOC);
        if (!preg_match('/^datetime/', $columnInfo['Type'])) {
            return false;
        }
        // Check valid_until column
        $result2 = $this->dbConn->query(
            'SHOW COLUMNS FROM ' . $this->tableName . ' LIKE \'valid_until\''
        );
        if ($result2->rowCount() != 1) {
            return false;
        }
        $columnInfo2 = $result2->fetch(PDO::FETCH_ASSOC);
        if (!preg_match('/^datetime/', $columnInfo2['Type'])) {
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
    public function createRowWithTime($theRow, string $time)
    {
        if (!isset($theRow['id']) || $theRow['id']===0) {
            $theRow['id'] = $this->getOneUnusedId();
            if ($theRow['id'] === false) {
                return false;
            }
        } else {
            if (!is_int($theRow['id'])) {
                return false;
            }
            if ($this->rowExistsById($theRow['id'])) {
                return false;
            }
        }
        return $this->realCreateRowWithTime($theRow, $time);
    }
    
    /**
     * Actual creation of a row
     *
     * @param type $theRow
     * @param type $time
     * @return boolean
     */
    protected function realCreateRowWithTime($theRow, $time)
    {
        if (!$this->isDbTableValid()) {
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
    public function makeRowInvalid($theRow, string $time)
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
     * @param type $time
     * @return type
     */
    public function realUpdateRowWithTime($theRow, string $time)
    {
        $oldRow = $this->realGetRow($theRow['id']);
        $this->makeRowInvalid($oldRow, $time);
        foreach (array_keys($oldRow) as $key) {
            if ($key === 'valid_from' or $key==='valid_until') {
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
    public function getAllRowsWithTime($time)
    {
        if (!$this->isDbTableValid()) {
            return false;
        }
        $time = $this->quote($time);
        $res = $this->dbConn->query('SELECT * FROM ' . $this->tableName .
                ' WHERE valid_from <= ' . $time .
                ' AND valid_until > ' . $time);
        if ($res === false) {
            // Can't get here in testing: query only fails on MySQL failure
            return false; // @codeCoverageIgnore
        }
        return $this->forceIntIds($res->fetchAll(PDO::FETCH_ASSOC));
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
        $res = $this->dbConn
                ->query($query)
                ->fetch(PDO::FETCH_ASSOC);
        
        if ($res === false) {
            // Can't get here in testing: query only fails on MySQL failure
            return false; // @codeCoverageIgnore
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
    public function realfindRowsWithTime($theRow, $maxResults, $time)
    {
        if (!$this->isDbTableValid()) {
            return false;
        }
        $keys = array_keys($theRow);
        $conditions = [];
        foreach ($keys as $key) {
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
        $res = $this->dbConn->query($sql);
        if ($res === false) {
            return false;
        }

        return $this->forceIntIds($res->fetchAll(PDO::FETCH_ASSOC));
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
        $timeNow = microtime(true);
        $intTime =  floor($timeNow);
        $date=date(self::MYSQL_DATE_FORMAT, $intTime);
        $microSeconds = (int) floor(($timeNow - $intTime)*1000000);
        return sprintf("%s.%06d", $date, $microSeconds);
    }
}
