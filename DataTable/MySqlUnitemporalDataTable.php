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
class MySqlUnitemporalDataTable extends MySqlDataTable implements UnitemporalDataTable
{
    
    // Error codes
    const ERROR_INVALID_TIME = 2010;
    const ERROR_NOT_IMPLEMENTED = 2011;
    
    
    // Other constants
    const FIELD_VALID_FROM = 'valid_from';
    const FIELD_VALID_UNTIL = 'valid_until';
    /**
     *
     * @param PDO $dbConnection initialized PDO connection
     * @param string $tableName SQL table name
     */
    public function __construct(PDO $dbConnection, string $tableName)
    {

        parent::__construct($dbConnection, $tableName);

        // Check additional columns
        if (!$this->isMySqlTableColumnValid(self::FIELD_VALID_FROM, 'datetime')) {
            // error message and code set by isMySqlTableColumnValid
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
        }

        if (!$this->isMySqlTableColumnValid(self::FIELD_VALID_UNTIL, 'datetime')) {
            // error message and code set by isMySqlTableColumnValid
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
        }

        // Override rowExistsById statement
        try {
            $this->statements['rowExistsById'] =
                $this->dbConn->prepare('SELECT id FROM ' . $this->tableName .
                        ' WHERE id= :id AND `' . self::FIELD_VALID_UNTIL . '`=' .
                        $this->quoteValue(UnitemporalDataTable::END_OF_TIMES));
        } catch (PDOException $e) { // @codeCoverageIgnore
            // @codeCoverageIgnoreStart
            $this->setError("Could not prepare statements "
                    . "in constructor, " . $e->getMessage(), self::ERROR_PREPARING_STATEMENTS);
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
     * Creates a new row that is valid from the given time and returns the new
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
     * Uses MySqlDataTable's realCreateRow to create a row since that method does
     * not check for already used Ids
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
        
        $theRow[self::FIELD_VALID_FROM] = $timeString;
        $theRow[self::FIELD_VALID_UNTIL] = UnitemporalDataTable::END_OF_TIMES;
        
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
                 self::FIELD_VALID_UNTIL . '=' . $this->quoteValue($timeString) .
                ' WHERE id=' . $theRow['id'] .
                ' AND ' . self::FIELD_VALID_FROM . ' = ' . $this->quoteValue($theRow[self::FIELD_VALID_FROM]) .
                ' AND ' . self::FIELD_VALID_UNTIL . '= ' . $this->quoteValue($theRow[self::FIELD_VALID_UNTIL]);
        
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
     * Updates the last version of a row marking the change as
     * occurring at the given $timeString
     *
     * @param array $theRow
     * @param string $timeString
     * @return void
     */
    public function realUpdateRowWithTime(array $theRow, string $timeString) : void
    {

        $timeString = self::getGoodTimeString($timeString);
        if (!self::isTimeStringValid($timeString)) {
            $this->throwExceptionForInvalidTime($timeString, 'realUpdateRowWithTime');
        }

        $oldRow = $this->realGetRow($theRow['id']);

        $this->makeRowInvalid($oldRow, $timeString);
        foreach (array_keys($oldRow) as $key) {
            if ($key === self::FIELD_VALID_FROM or $key === self::FIELD_VALID_UNTIL) {
                continue;
            }
            if (!array_key_exists($key, $theRow)) {
                $theRow[$key] = $oldRow[$key];
            }
        }
        $this->realCreateRowWithTime($theRow, $timeString);
    }


    public function search(array $searchSpec, int $searchType = self::SEARCH_AND, int $maxResults = 0): array
    {

        // Provisional implementation: get a full search a filter out rows not valid at the current time

        $results = parent::search($searchSpec, $searchType, $maxResults);

        $filteredResults = [];

        foreach($results as $row) {
            if ($row[self::FIELD_VALID_UNTIL] === UnitemporalDataTable::END_OF_TIMES) {
                $filteredResults[] = $row;
            }
        }

        return  $filteredResults;
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
                ' WHERE ' . self::FIELD_VALID_FROM . '<=' . $quotedTimeString .
                ' AND '. self::FIELD_VALID_UNTIL .  '>' . $quotedTimeString;
        
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
            unset($theRow[self::FIELD_VALID_FROM]);
            unset($theRow[self::FIELD_VALID_UNTIL]);
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
     * @return array
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
                        ' AND `'. self::FIELD_VALID_FROM . '`<=' . $quotedTimeString .
                        ' AND `'. self::FIELD_VALID_UNTIL . '`>' . $quotedTimeString .
                        ' LIMIT 1';
        
        $r = $this->doQuery($sql, 'getRowWithTime');

        $res = $r->fetch(PDO::FETCH_ASSOC);
        if ($res === false) {
            $this->setError('The row with id ' . $rowId . ' does not exist', self::ERROR_ROW_DOES_NOT_EXIST);
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
            if ($key === self::FIELD_VALID_FROM or $key ===self::FIELD_VALID_UNTIL) {
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
                ' AND `'. self::FIELD_VALID_FROM . '`<=' . $quotedTimeString .
                ' AND `'. self::FIELD_VALID_UNTIL . '`>' . $quotedTimeString;

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
                // However, just in case this may be hiding something else,
                // let's report everything in the error message
                $this->setError('Query error in realFindRowsWithTime (reported as no results) : ' .
                        $e->getMessage() . ' :: query = ' . $sql, self::ERROR_EMPTY_RESULT_SET);
                return [];
            }
            // @codeCoverageIgnoreStart
            $this->setError('Query error in realFindRowsWithTime: ' . $e->getMessage() . ' :: query = ' . $sql,
                self::ERROR_MYSQL_QUERY_ERROR);
            return [];
            // @codeCoverageIgnoreEnd
        }
        if ($r === false) {
            // @codeCoverageIgnoreStart
            $this->setError('Unknown error in realFindRowsWithTime when executing query: ' . $sql,
                self::ERROR_UNKNOWN_ERROR);
            return [];
            // @codeCoverageIgnoreEnd
        }
        
        
        return $this->forceIntIds($r->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param int $rowId
     * @return int
     * @throws Exception
     */
    public function deleteRow(int $rowId): int
    {
        return $this->deleteRowWithTime($rowId, self::now());
    }


    /**
     * 'Deletes' a row by making its current version invalid
     * as of the given time.
     *
     * It can only delete the latest valid version of the row, not previous ones.
     *
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
                throw $e; // @codeCoverageIgnore
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
        return TimeString::now();
    }

    /**
     * @param float $timeStamp
     * @return string
     */
    public static function getTimeStringFromTimeStamp(float $timeStamp) : string
    {
        return TimeString::getTimeStringFromTimeStamp($timeStamp);
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
        return TimeString::getTimeStringFromVariable($timeVar);
    }


    public static function getGoodTimeString(string $str) {
       return TimeString::getGoodTimeString($str);
    }
    /**
     * Returns true if the given string is a valid timeString
     *
     * @param string $str
     * @return bool
     */
    public static function isTimeStringValid(string $str) : bool {
      return TimeString::isTimeStringValid($str);
    }

    private function throwExceptionForInvalidTime(string $timeString, string $context) : void {
        $this->setError("Invalid time given for $context : \"$timeString\"", self::ERROR_INVALID_TIME);
        throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
    }

    public function rowExistsWithTime(int $rowId, string $timeString): bool
    {
        // TODO: fix this implementation!!
        // Very inefficient implementation using getRowWithTime
        try {
            $this->getRowWithTime($rowId, $timeString);
        } catch(InvalidArgumentException $e) {
            if ($e->getCode() === self::ERROR_ROW_DOES_NOT_EXIST) {
                return false;
            }
            throw $e;
        }

        return true;
    }

    public function searchWithTime(array $searchSpec, int $searchType, string $timeString, int $maxResults = 0): array
    {
        // TODO: implement searchWithTime
        $this->setError('Full search with time not implemented yet', self::ERROR_NOT_IMPLEMENTED);
        return [];

    }

    public function updateRowWithTime(array $theRow, string $timeString): void
    {
        $this->resetError();
        if (!isset($theRow['id']))  {
            $this->setError('Id not set in given row, cannot update', self::ERROR_ID_NOT_SET);
            throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
        }

        if ($theRow['id']===0) {
            $this->setError('Id is equal to zero in given row, cannot update', self::ERROR_ID_IS_ZERO);
            throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
        }
        if (!is_int($theRow['id'])) {
            $this->setError('Id in given row is not an integer, cannot update', self::ERROR_ID_NOT_INTEGER);
            throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
        }

        $this->realUpdateRowWithTime($theRow, $timeString);
    }
}
