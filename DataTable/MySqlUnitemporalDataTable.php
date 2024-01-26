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
use PDOException;
use RuntimeException;
use ThomasInstitut\TimeString\TimeString;

/**
 * Implements a MySql data table that keeps different versions
 * of its rows. The term 'unitemporal' is taken from
 * Johnston and Weis, "Managing Time in Relational Databases", 2010, but
 * this implementation does not necessarily follow the techniques
 * described in that book.
 *
 * The normal DataTable methods for creating, updating and deleting
 * rows do not delete any previous data but just mark that data as
 * not valid anymore. Data retrieval methods (getRow and findRows) get
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

    const REPORT_TYPE_ERROR = 'error';
    const REPORT_TYPE_WARNING = 'warning';
    const REPORT_TYPE_INFO  = 'info';

    const REPORT_ERROR_INVALID_TIME_RANGE = 100;
    const REPORT_WARNING_ZERO_TIME_RANGE = 101;
    const REPORT_ERROR_OVERLAPPING_VERSIONS = 102;
    const REPORT_INFO_GAP = 103;

    
    
    // Other constants
    const FIELD_VALID_FROM = 'valid_from';
    const FIELD_VALID_UNTIL = 'valid_until';

    /**
     *
     * @param PDO $dbConnection initialized PDO connection
     * @param string $tableName SQL table name
     */
    public function __construct(PDO $dbConnection, string $tableName, string $idColumnName = self::DEFAULT_ID_COLUMN_NAME)
    {

        parent::__construct($dbConnection, $tableName, false, $idColumnName);

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
                $this->dbConn->prepare("SELECT `$this->idColumnName` FROM " . $this->tableName .
                        " WHERE `$this->idColumnName`= :id AND `" . self::FIELD_VALID_UNTIL . '`=' .
                        $this->quoteValue(TimeString::END_OF_TIMES));
        } catch (PDOException $e) { // @codeCoverageIgnore
            // @codeCoverageIgnoreStart
            $this->setError("Could not prepare statements "
                    . "in constructor, " . $e->getMessage(), self::ERROR_PREPARING_STATEMENTS);
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Checks that the data's time information is consistent across the table
     * Returns an array of objects each one describing an issue:
     *    [
     *         'id' => int
     *         'type' =>  ERROR | WARNING | INFO
     *         'code' =>  int
     *         'description'  => string
     *
     *   ]
     * @param array $ids
     * @return array
     * @throws InvalidArgumentException
     * @throws RunTimeException
     */
    public function checkConsistency(array $ids = []) : array {
        $issues = [];
        if (count($ids) === 0) {
            // check everything!
            $ids = $this->getUniqueIdsWithTime('');
        }

        foreach ($ids as $id) {
            $id = intval($id);  // just in case
            if ($id < 0) {
                continue;
            }
            $rowHistory = $this->getRowHistory($id);
            //print_r($rowHistory);
            $previousVersion = null;
            foreach ($rowHistory as $version) {
                if ($version[self::FIELD_VALID_UNTIL] < $version[self::FIELD_VALID_FROM]) {
                    $issues[] = [
                        'id' => $id, 'type' => self::REPORT_TYPE_ERROR,
                        'code' => self::REPORT_ERROR_INVALID_TIME_RANGE,
                        'description' => "validUntil " . $version[self::FIELD_VALID_UNTIL] . " < validFrom " . $version[self::FIELD_VALID_FROM]
                    ];
                }
                if ($version[self::FIELD_VALID_UNTIL] === $version[self::FIELD_VALID_FROM]) {
                    $issues[] = [
                        'id' => $id, 'type' => self::REPORT_TYPE_WARNING,
                        'code' => self::REPORT_WARNING_ZERO_TIME_RANGE,
                        'description' => "validUntil " . $version[self::FIELD_VALID_UNTIL] . " = validFrom " . $version[self::FIELD_VALID_FROM]
                    ];
                }
                if (!is_null($previousVersion)) {
                    if ($version[self::FIELD_VALID_FROM] < $previousVersion[self::FIELD_VALID_UNTIL]) {
                        $issues[] = [
                            'id' => $id, 'type' => self::REPORT_TYPE_ERROR,
                            'code' => self::REPORT_ERROR_OVERLAPPING_VERSIONS,
                            'description' => "validFrom " . $version[self::FIELD_VALID_FROM] . " < previous version validUntil " . $previousVersion[self::FIELD_VALID_UNTIL]
                        ];
                    }
                    if ($version[self::FIELD_VALID_FROM] >  $previousVersion[self::FIELD_VALID_UNTIL]) {
                        $issues[] = [
                            'id' => $id, 'type' => self::REPORT_TYPE_INFO,
                            'code' => self::REPORT_INFO_GAP,
                            'description' => "validFrom " . $version[self::FIELD_VALID_FROM] . " > previous version validUntil " . $previousVersion[self::FIELD_VALID_UNTIL]
                        ];
                    }
                }
                $previousVersion = $version;
            }
        }
        return $issues;
    }

    /**
     * Get all unique Ids in the table at the given time,
     * If the given time is not a valid timeString returns
     * all uniqueIds regardless of time.
     * @param string $timeString
     * @return array
     * @throws RunTimeException
     */
    public function getUniqueIdsWithTime(string $timeString) : array{

        $timeString = TimeString::fromString($timeString);
        $sqlTimeConstraint  = '';
        if (TimeString::isValid($timeString)) {
            $quotedTimeString = $this->quoteValue($timeString);
            $sqlTimeConstraint =   ' WHERE ' . self::FIELD_VALID_FROM . '<=' . $quotedTimeString .
               ' AND '. self::FIELD_VALID_UNTIL .  '>' . $quotedTimeString;
        }

        $tableName = $this->tableName;
        $idColumn = $this->idColumnName;

        $sql = "SELECT DISTINCT($idColumn) FROM " . $this->tableName . $sqlTimeConstraint . " ORDER BY `$tableName`.`$idColumn`";

        $result = $this->doQuery($sql, "getUniqueIds");
        $ids = array_map( function ($row) use ($idColumn): int { return intval($row[$idColumn]);}, $result->fetchAll());
        sort($ids, SORT_NUMERIC);
        return $ids;
    }



    public function getUniqueIds(): array
    {
        return $this->getUniqueIdsWithTime(TimeString::now());
    }


    /**
     * Creates a row valid from the current time.
     *
     * @param array $theRow
     * @return int
     * @throws InvalidTimeStringException
     */
    public function realCreateRow(array $theRow) : int
    {
        return $this->realCreateRowWithTime($theRow, TimeString::now());
    }

    /**
     * Creates a new row that is valid from the given time and returns the new
     * row's id
     *
     * @param array $theRow
     * @param string $timeString in MySql format, e.g., '2010-09-20 18:25:25'
     * @return int
     * @throws RowAlreadyExists
     * @throws InvalidTimeStringException
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
     * @throws InvalidTimeStringException
     */
    protected function realCreateRowWithTime(array $theRow, string $timeString) : int
    {

        if ($timeString === '') {
            $timeString = TimeString::now();
        } else {
            $timeString = TimeString::fromString($timeString);
            if (!TimeString::isValid($timeString)) {
                $this->throwExceptionForInvalidTime($timeString, 'realCreateRowWithTime');
            }
        }

        $theRow[self::FIELD_VALID_FROM] = $timeString;
        $theRow[self::FIELD_VALID_UNTIL] = TimeString::END_OF_TIMES;
        
        return parent::realCreateRow($theRow);
    }


    /**
     * Makes a row invalid from the given time
     *
     * @param array $theRow
     * @param string $timeString
     * @return int
     * @throws InvalidTimeStringException
     */
    protected function makeRowInvalid(array $theRow, string $timeString) : int
    {
        $timeString = TimeString::fromString($timeString);
        if (!TimeString::isValid($timeString)) {
            $this->throwExceptionForInvalidTime($timeString, 'makeRowInvalid');
        }
        $sql = 'UPDATE ' . $this->tableName . ' SET ' .
                 self::FIELD_VALID_UNTIL . '=' . $this->quoteValue($timeString) .
                " WHERE `$this->idColumnName`=" . $theRow[$this->idColumnName] .
                ' AND ' . self::FIELD_VALID_FROM . ' = ' . $this->quoteValue($theRow[self::FIELD_VALID_FROM]) .
                ' AND ' . self::FIELD_VALID_UNTIL . '= ' . $this->quoteValue($theRow[self::FIELD_VALID_UNTIL]);
        
        $this->doQuery($sql, 'makeRowInvalid');

        return $theRow[$this->idColumnName];
    }

    /**
     * Updates a row
     * @param array $theRow
     * @return void
     * @throws RowDoesNotExist
     */
    public function realUpdateRow(array $theRow) : void
    {
        try {
            $this->realUpdateRowWithTime($theRow, TimeString::now());
        } catch(InvalidTimeStringException) {
            // should never happen
        }

    }

    /**
     * Updates the last version of a row marking the change as
     * occurring at the given $timeString
     *
     * @param array $theRow
     * @param string $timeString
     * @return void
     * @throws InvalidTimeStringException
     * @throws RowDoesNotExist
     */
    public function realUpdateRowWithTime(array $theRow, string $timeString) : void
    {

        $timeString = TimeString::fromString($timeString);
        if (!TimeString::isValid($timeString)) {
            $this->throwExceptionForInvalidTime($timeString, 'realUpdateRowWithTime');
        }

        $oldRow = $this->realGetRow($theRow[$this->idColumnName]);

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


    /**
     * Returns the sql query needed to the get the search results
     *
     * @param array $searchSpecArray
     * @param int $searchType
     * @param int $maxResults
     * @return string
     */
    protected function getSearchSqlQuery(array $searchSpecArray, int $searchType, int $maxResults) : string {

        $conditions = [];
        foreach ($searchSpecArray as $spec) {
            $conditions[] = $this->getSqlConditionFromSpec($spec);
        }
        $sqlLogicalOperator  = 'AND';
        if ($searchType == self::SEARCH_OR) {
            $sqlLogicalOperator = 'OR';
        }
        $sql = 'SELECT * FROM `' . $this->tableName . '` WHERE '
            .  implode(' ' . $sqlLogicalOperator . ' ', $conditions);

        $validUntilColumn = self::FIELD_VALID_UNTIL;
        $eot = TimeString::END_OF_TIMES;

        $sql .= " AND `$validUntilColumn`='$eot'";

        if ($maxResults > 0) {
            $sql .= ' LIMIT ' . $maxResults;
        }

        return $sql;
    }

    public function getAllRows() : DataTableResultsIterator
    {
        try {
            $iterator = $this->getAllRowsWithTime(TimeString::now());
        } catch (InvalidTimeStringException) {
            // should never happen
        }
        return  $iterator ?? new DataTableResultsArrayIterator([]);
    }


    /**
     * @throws InvalidTimeStringException
     */
    public function getAllRowsWithTime(string $timeString) : DataTableResultsIterator
    {
        $this->resetError();

        $timeString = TimeString::fromString($timeString);
        if (!TimeString::isValid($timeString)) {
            $this->throwExceptionForInvalidTime($timeString, 'getAllRowsWithTime');
        }
        $quotedTimeString = $this->quoteValue($timeString);
        $sql = 'SELECT * FROM ' . $this->tableName .
                ' WHERE ' . self::FIELD_VALID_FROM . '<=' . $quotedTimeString .
                ' AND '. self::FIELD_VALID_UNTIL .  '>' . $quotedTimeString;
        
        return new DataTableResultsPdoIterator($this->doQuery($sql, 'getAllRowsWithTime'), $this->idColumnName);
    }

    public function getRow(int $rowId) : array
    {
        $this->resetError();
        return $this->realGetRow($rowId, true);
    }


    /**
     * @throws RowDoesNotExist
     */
    public function realGetRow(int $rowId, bool $stripTimeInfo = false) : array
    {

        try {
            $theRow = $this->getRowWithTime($rowId, TimeString::now());
        } catch (InvalidTimeStringException) {
            // impossible if TimeString::now() is working
        }
        $theRow = $theRow ?? [];
        if ($stripTimeInfo) {
            unset($theRow[self::FIELD_VALID_FROM]);
            unset($theRow[self::FIELD_VALID_UNTIL]);
        }
        return $theRow;
    }


    /**
     * @throws InvalidTimeStringException
     * @throws RowDoesNotExist
     */
    public function getRowWithTime(int $rowId, string $timeString) : array
    {
        $this->resetError();

        $timeString = TimeString::fromString($timeString);
        if (!TimeString::isValid($timeString)) {
            $this->throwExceptionForInvalidTime($timeString, 'getRowWithTime');
        }

        $quotedTimeString = $this->quoteValue($timeString);

        $sql = 'SELECT * FROM ' . $this->tableName .
                        " WHERE `$this->idColumnName`=" . $rowId .
                        ' AND `'. self::FIELD_VALID_FROM . '`<=' . $quotedTimeString .
                        ' AND `'. self::FIELD_VALID_UNTIL . '`>' . $quotedTimeString .
                        ' LIMIT 1';
        
        $r = $this->doQuery($sql, 'getRowWithTime');

        $res = $r->fetch(PDO::FETCH_ASSOC);
        if ($res === false) {
            $this->setError('The row with id ' . $rowId . ' does not exist', self::ERROR_ROW_DOES_NOT_EXIST);
            throw new RowDoesNotExist($this->getErrorMessage(), $this->getErrorCode());
        }
        $res[$this->idColumnName] = (int) $res[$this->idColumnName];
        return $res;
    }

    public function findRows(array $rowToMatch, int $maxResults = 0) : DataTableResultsIterator
    {
        try {
            return $this->findRowsWithTime($rowToMatch, $maxResults, TimeString::now());
        } catch (InvalidTimeStringException) {
            // should never happen
        }
        return new DataTableResultsArrayIterator([]);
    }


    /**
     * @throws InvalidTimeStringException
     */
    public function findRowsWithTime($theRow, $maxResults, string $timeString) : DataTableResultsIterator
    {
        $this->resetError();

        $timeString = TimeString::fromString($timeString);
        if (!TimeString::isValid($timeString)) {
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
                return new DataTableResultsArrayIterator([]);
            }
            // @codeCoverageIgnoreStart
            $this->setError('Query error in realFindRowsWithTime: ' . $e->getMessage() . ' :: query = ' . $sql,
                self::ERROR_MYSQL_QUERY_ERROR);
            return new DataTableResultsArrayIterator([]);
            // @codeCoverageIgnoreEnd
        }
        if ($r === false) {
            // @codeCoverageIgnoreStart
            $this->setError('Unknown error in realFindRowsWithTime when executing query: ' . $sql,
                self::ERROR_UNKNOWN_ERROR);
            return new DataTableResultsArrayIterator([]);
            // @codeCoverageIgnoreEnd
        }
        return new DataTableResultsPdoIterator($r, $this->idColumnName);
    }


    public function deleteRow(int $rowId): int
    {
        try {
            return $this->deleteRowWithTime($rowId, TimeString::now());
        } catch (InvalidTimeStringException) {
        }
        return 0;
    }


    /**
     * 'Deletes' a row by making its current version invalid as of the given time.
     *
     * It can only delete the latest valid version of the row, not previous ones.
     *
     * Returns 1 if the row was deleted, 0 if the row did not exist in the first place.
     *
     * @param int $rowId
     * @param string $timeString
     * @return int
     * @throws InvalidTimeStringException
     */
    public function deleteRowWithTime(int $rowId, string $timeString) : int
    {
        try {
            $oldRow = $this->realGetRow($rowId);
        } catch (RowDoesNotExist) {
            return  0;
        }
        $this->makeRowInvalid($oldRow, $timeString);
        return 1;
    }


    /**
     * @throws InvalidTimeStringException
     */
    private function throwExceptionForInvalidTime(string $timeString, string $context) : void {
        $this->setError("Invalid time given for $context : \"$timeString\"", self::ERROR_INVALID_TIME);
        throw new InvalidTimeStringException($this->getErrorMessage(), $this->getErrorCode());
    }


    /**
     * @throws InvalidTimeStringException
     * @throws RowDoesNotExist
     */
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
            throw $e; // @codeCoverageIgnore
        }

        return true;
    }

    public function searchWithTime(array $searchSpecArray, int $searchType, string $timeString, int $maxResults = 0): DataTableResultsIterator
    {
        // TODO: implement searchWithTime
        $this->setError('Full search with time not implemented yet', self::ERROR_NOT_IMPLEMENTED);
        return new DataTableResultsArrayIterator([]);

    }


    /**
     * @throws InvalidTimeStringException
     * @throws RowDoesNotExist
     * @throws InvalidRowForUpdate
     */
    public function updateRowWithTime(array $theRow, string $timeString): void
    {
        $this->resetError();
        if (!$this->isRowIdGoodForRowUpdate($theRow, 'MySqlUnitemporalDataTable updateRowWithTime')) {
            throw new InvalidRowForUpdate($this->getErrorMessage(), $this->getErrorCode());
        }
        $this->realUpdateRowWithTime($theRow, $timeString);
    }

    /**
     * Returns an array with all the different versions of the row with the given $rowId
     * Each version has the same fields as any row in the datatable plus
     *  'valid_from' and a 'valid_until' fields.
     *
     * @param int $rowId
     * @return array
     * @throws RowDoesNotExist
     */
    public function getRowHistory(int $rowId): array
    {
        $sql = 'SELECT * FROM ' . $this->tableName .
            ' WHERE `'. $this->idColumnName. '`=' . $rowId . ' ORDER BY `'. self::FIELD_VALID_FROM . '`' ;

        $r = $this->doQuery($sql, 'getRowHistory');
        if ($r->rowCount() === 0) {
            $this->setError('The row with id ' . $rowId . ' has never existed', self::ERROR_ROW_DOES_NOT_EXIST);
            throw new RowDoesNotExist($this->getErrorMessage(), $this->getErrorCode());
        }

        $rows = $r->fetchAll(PDO::FETCH_ASSOC);

        return $this->forceIntIds($rows);
    }
}
