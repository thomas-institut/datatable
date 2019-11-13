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

use Exception;
use InvalidArgumentException;
use \PDO;
use \PDOException;
use RuntimeException;

/**
 * Implements a MySql data table that keeps different versions
 * of its rows. The term 'unitemporal' is taken from
 * Johnston and Weis, 'Managing Time in Relational Databases", 2010, but
 * this implementation does not necessarily follow the techniques
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
 * The class should work for any system that implements microtime(),
 * see http://php.net/manual/en/function.microtime.php
 *
 * @author Rafael Nájera <rafael@najera.ca>
 */
class MySqlUnitemporalDataTable extends MySqlDataTable
{
    
    // Error codes
    const ERROR_INVALID_TIME = 2010;
    
    
    // Other constants
    const END_OF_TIMES = '9999-12-31 23:59:59.999999';
    const MYSQL_DATE_FORMAT  = 'Y-m-d H:i:s';

    /**
     *
     * @param PDO $dbConnection initialized PDO connection
     * @param string $tableName SQL table name
     */
    public function __construct(PDO $dbConnection, string $tableName)
    {

        parent::__construct($dbConnection, $tableName);

        // Check additional columns
        if (!$this->isMySqlTableColumnValid('valid_from', 'datetime')) {
            // error message and code set by isMySqlTableColumnValid
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
        }

        if (!$this->isMySqlTableColumnValid('valid_until', 'datetime')) {
            // error message and code set by isMySqlTableColumnValid
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
        }

        // Override rowExistsById statement
        try {
            $this->statements['rowExistsById'] =
                $this->dbConn->prepare('SELECT id FROM ' . $this->tableName .
                        ' WHERE id= :id AND valid_until=' .
                        $this->quoteValue(self::END_OF_TIMES));
        } catch (PDOException $e) { // @codeCoverageIgnore
            // @codeCoverageIgnoreStart
            $this->setErrorCode(self::ERROR_PREPARING_STATEMENTS);
            $this->setErrorMessage("Could not prepare statements "
                    . "in constructor, " . $e->getMessage());
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Creates a row valid from the current time.
     *
     * @param array $theRow
     * @return int
     */
    public function realCreateRow(array $theRow) : int
    {
        return $this->realCreateRowWithTime($theRow, self::now());
    }

    /**
     * Creates a row valid from the given time and returns the new
     * row's id
     *
     * @param array $theRow
     * @param string $timeString in MySql format, e.g., '2010-09-20 18:25:25'
     * @return int
     */
    public function createRowWithTime(array $theRow, string $timeString) : int
    {
        $this->resetError();
        $preparedRow = $this->getRowWithGoodIdForCreation($theRow);
        return $this->realCreateRowWithTime($preparedRow, $timeString);
    }

    /**
     * Actual creation of a row
     *
     * @param array $theRow
     * @param string $timeString
     * @return int
     */
    protected function realCreateRowWithTime(array $theRow, string $timeString) : int
    {

        $timeString = self::getGoodTimeString($timeString);
        if (!self::isTimeStringValid($timeString)) {
            $this->throwExceptionForInvalidTime($timeString, 'realCreateRowWithTime');
        }
        
        $theRow['valid_from'] = $timeString;
        $theRow['valid_until'] = self::END_OF_TIMES;
        
        return parent::realCreateRow($theRow);
    }

    /**
     * Makes a row invalid from the given time
     *
     * @param array $theRow
     * @param string $timeString
     * @return int
     */
    protected function makeRowInvalid(array $theRow, string $timeString) : int
    {
        $timeString = self::getGoodTimeString($timeString);
        if (!self::isTimeStringValid($timeString)) {
            $this->throwExceptionForInvalidTime($timeString, 'makeRowInvalid');
        }
        $sql = 'UPDATE ' . $this->tableName . ' SET ' .
                ' valid_until=' . $this->quoteValue($timeString).
                ' WHERE id=' . $theRow['id'] .
                ' AND valid_from = ' . $this->quoteValue($theRow['valid_from']) .
                ' AND valid_until= ' . $this->quoteValue($theRow['valid_until']);
        
        $this->doQuery($sql, 'makeRowInvalid');

        return $theRow['id'];
    }

    /**
     * Updates a row keeping the current version
     * @param array $theRow
     * @return void
     */
    public function realUpdateRow(array $theRow) : void
    {
        $this->realUpdateRowWithTime($theRow, self::now());
    }
    
    /**
     * Updates a row at the given time
     * @param array $theRow
     * @param $timeString
     * @return bool
     */
    public function realUpdateRowWithTime(array $theRow, string $timeString) : bool
    {

        $timeString = self::getGoodTimeString($timeString);
        if (!self::isTimeStringValid($timeString)) {
            $this->throwExceptionForInvalidTime($timeString, 'realUpdateRowWithTime');
        }

        $oldRow = $this->realGetRow($theRow['id']);
//        if ($oldRow === []) {
//            $this->setError('Row not found, cannot update', self::ERROR_ROW_DOES_NOT_EXIST);
//            throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
//        }
        $this->makeRowInvalid($oldRow, $timeString);
        foreach (array_keys($oldRow) as $key) {
            if ($key === 'valid_from' or $key ==='valid_until') {
                continue;
            }
            if (!array_key_exists($key, $theRow)) {
                $theRow[$key] = $oldRow[$key];
            }
        }
        return $this->realCreateRowWithTime($theRow, $timeString);
    }
    
    /**
     * Returns all rows that are valid at the present moment
     *
     * @return array
     */
    public function getAllRows() : array
    {
        return $this->getAllRowsWithTime(self::now());
    }

    /**
     * Returns all row that are/were valid that the given time
     *
     * @param $timeString
     * @return array
     */
    public function getAllRowsWithTime(string $timeString) : array
    {
        $this->resetError();

        $timeString = self::getGoodTimeString($timeString);
        if (!self::isTimeStringValid($timeString)) {
            $this->throwExceptionForInvalidTime($timeString, 'getAllRowsWithTime');
        }
        $quotedTimeString = $this->quoteValue($timeString);
        $sql = 'SELECT * FROM ' . $this->tableName .
                ' WHERE valid_from <= ' . $quotedTimeString .
                ' AND valid_until > ' . $quotedTimeString;
        
        $r = $this->doQuery($sql, 'getAllRowsWithTime');

        return $this->forceIntIds($r->fetchAll(PDO::FETCH_ASSOC));
    }
    
    /**
     * Returns the version of the row with the given ID that is
     * valid at the current time.
     *
     * For compatibility with DataTable, it strips time information
     *
     * @param int $rowId
     * @return array
     */
    public function getRow(int $rowId) : array
    {
        $this->resetError();
        return $this->realGetRow($rowId, true);
    }

    /**
     * Returns the version of the row with the given ID that is
     * valid at the current time, optionally stripping time
     * information from the resulting array.
     *
     * @param int $rowId
     * @param bool $stripTimeInfo
     * @return array
     */
    public function realGetRow(int $rowId, bool $stripTimeInfo = false) : array
    {
        $theRow = $this->getRowWithTime($rowId, self::now());

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
     * @param int $rowId
     * @param string $timeString
     * @return array|boolean
     */
    public function getRowWithTime(int $rowId, string $timeString) : array
    {
        $this->resetError();

        $timeString = self::getGoodTimeString($timeString);
        if (!self::isTimeStringValid($timeString)) {
            $this->throwExceptionForInvalidTime($timeString, 'getRowWithTime');
        }

        $quotedTimeString = $this->quoteValue($timeString);

        $sql = 'SELECT * FROM ' . $this->tableName .
                        ' WHERE `id`=' . $rowId .
                        ' AND `valid_from`<=' . $quotedTimeString .
                        ' AND `valid_until`>' . $quotedTimeString .
                        ' LIMIT 1';
        
        $r = $this->doQuery($sql, 'getRowWithTime');

        $res = $r->fetch(PDO::FETCH_ASSOC);
        if ($res === false) {
            $this->setErrorMessage('The row with id ' . $rowId . ' does not exist');
            $this->setErrorCode(self::ERROR_ROW_DOES_NOT_EXIST);
            throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
        }
        $res['id'] = (int) $res['id'];
        return $res;
    }

    /**
     * Finds rows that are valid at the current moment.
     * The rows in the resulting array will contain time information.
     * @param array $rowToMatch
     * @param int $maxResults
     * @return array
     */
    public function findRows(array $rowToMatch, int $maxResults = 0) : array
    {
        return $this->findRowsWithTime($rowToMatch, $maxResults, self::now());
    }

    /**
     * Finds rows that are/were valid at the given time
     *
     * @param array $theRow
     * @param int $maxResults
     * @param $timeString
     * @return array
     */
    public function findRowsWithTime($theRow, $maxResults, string $timeString) : array
    {
        $this->resetError();

        $timeString = self::getGoodTimeString($timeString);
        if (!self::isTimeStringValid($timeString)) {
           $this->throwExceptionForInvalidTime($timeString, 'findRowsWithTime');
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
        $quotedTimeString = $this->quoteValue($timeString);
        $sql = 'SELECT * FROM ' . $this->tableName . ' WHERE ' .
                implode(' AND ', $conditions) .
                ' AND valid_from <= ' . $quotedTimeString .
                ' AND valid_until > ' . $quotedTimeString;
        if ($maxResults > 0) {
            $sql .= ' LIMIT ' . $maxResults;
        }

        try {
            $r = $this->dbConn->query($sql);
        } catch (PDOException $e) {
            if ( $e->getCode() === '42000') {
                // The exception was thrown because of an SQL syntax error but
                // this should only happen when one of the keys does not exist or
                // is of the wrong type. This just means that the search
                // did not have any results, so let's set the error code
                // to be 'empty result set'
                $this->setErrorCode(self::ERROR_EMPTY_RESULT_SET);
                // However, just in case this may be hiding something else, 
                // let's report everything in the error message
                $this->setErrorMessage('Query error in realFindRowsWithTime (reported as no results) : ' . 
                        $e->getMessage() . ' :: query = ' . $sql);
                return [];
            }
            // @codeCoverageIgnoreStart
            $this->setErrorCode(self::ERROR_MYSQL_QUERY_ERROR);
            $this->setErrorMessage('Query error in realFindRowsWithTime: ' . $e->getMessage() . ' :: query = ' . $sql);
            return [];
            // @codeCoverageIgnoreEnd
        }
        if ($r === false) {
            // @codeCoverageIgnoreStart
            $this->setErrorCode(self::ERROR_UNKNOWN_ERROR);
            $this->setErrorMessage('Unknown error in realFindRowsWithTime when executing query: ' . $sql);
            return [];
            // @codeCoverageIgnoreEnd
        }
        
        
        return $this->forceIntIds($r->fetchAll(PDO::FETCH_ASSOC));
    }

    public function deleteRow(int $rowId): int
    {
        return $this->deleteRowWithTime($rowId, self::now());
    }


    /**
     * 'Deletes' a row by making its current version invalid
     * as of the given time.
     * Returns the number of actually deleted
     *
     * @param int $rowId
     * @param string $timeString
     * @return int
     * @throws Exception
     */
    public function deleteRowWithTime(int $rowId, string $timeString) : int
    {
        try {
            $oldRow = $this->realGetRow($rowId);
        } catch (Exception $e) {
            if ($e->getCode() === self::ERROR_ROW_DOES_NOT_EXIST) {
                return 0;
            } else {
                throw $e;
            }
        }
        $this->makeRowInvalid($oldRow, $timeString);
        return 1;
    }


    /**
     * Returns the current time in MySQL format with microsecond precision
     *
     * @return string
     */
    public static function now() : string
    {
        return self::getTimeStringFromTimeStamp(microtime(true));
    }

    /**
     * @param float $timeStamp
     * @return string
     */
    private static function getTimeStringFromTimeStamp(float $timeStamp) : string
    {
        $intTime =  floor($timeStamp);
        $date=date(self::MYSQL_DATE_FORMAT, $intTime);
        $microSeconds = (int) floor(($timeStamp - $intTime)*1000000);
        return sprintf("%s.%06d", $date, $microSeconds);
    }

    /**
     * Returns a valid timeString if the variable can be converted to a time
     * If not, returns an empty string (which will be immediately recognized as
     * invalid by isTimeStringValid
     *
     * @param float|int|string $timeVar
     * @return string
     */
    public static function getTimeStringFromVariable($timeVar) : string
    {
        if (is_numeric($timeVar)) {
            return self::getTimeStringFromTimeStamp((float) $timeVar);
        }
        if (is_string($timeVar)) {
            return  self::getGoodTimeString($timeVar);
        }
        return '';
    }


    public static function getGoodTimeString(string $str) {
        if (preg_match('/^\d\d\d\d-\d\d-\d\d$/', $str)) {
            $str .= ' 00:00:00.000000';
        } else {
            if (preg_match('/^\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d$/', $str)){
                $str .= '.000000';
            }
        }
        if (!self::isTimeStringValid($str)) {
            return '';
        }
        return $str;
    }
    /**
     * Returns true if the given string is a valid timeString
     *
     * @param string $str
     * @return bool
     */
    public static function isTimeStringValid(string $str) : bool {
        if ($str === '') {
            return false;
        }
        $matches = [];
        if (preg_match('/^\d\d\d\d-(\d\d)-(\d\d) (\d\d):(\d\d):(\d\d)\.\d\d\d\d\d\d$/', $str, $matches) !== 1) {
            return false;
        }
        if (intval($matches[1]) > 12) {
            return false;
        }
        if (intval($matches[2]) > 31) {
            return false;
        }
        if (intval($matches[3]) > 23) {
            return false;
        }
        if (intval($matches[4]) > 59) {
            return false;
        }
        if (intval($matches[5]) > 59) {
            return false;
        }
        return true;
    }

    private function throwExceptionForInvalidTime(string $timeString, string $context) : void {
        $this->setErrorCode(self::ERROR_INVALID_TIME);
        $this->setErrorMessage("Invalid time given for $context : \"$timeString\"");
        throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
    }
}
