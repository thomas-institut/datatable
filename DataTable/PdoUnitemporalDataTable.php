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

use Iterator;
use Override;
use PDO;
use PDOException;
use RuntimeException;
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\DataTable\Exception\InvalidRowForUpdate;
use ThomasInstitut\DataTable\Exception\InvalidRowUpdateTime;
use ThomasInstitut\DataTable\Exception\InvalidTimeStringException;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\Exception\RowDoesNotExist;
use ThomasInstitut\DataTable\PdoProvider\PdoProvider;
use ThomasInstitut\DataTable\ResultsIterator\ArrayResultsIterator;
use ThomasInstitut\DataTable\ResultsIterator\PdoResultsIterator;
use ThomasInstitut\DataTable\ResultsIterator\ResultsIterator;
use ThomasInstitut\DataTable\SqlDialect\SqlDialect;
use ThomasInstitut\DataTable\UnitemporalConsistency\UnitemporalConsistencyChecker;
use ThomasInstitut\TimeString\InvalidTimeZoneException;
use ThomasInstitut\TimeString\MalformedStringException;
use ThomasInstitut\TimeString\TimeString;
use Throwable;

/**
 * Implements a PDO-based data table that keeps different versions
 * of its rows.
 *
 * The actual SQL table should have an integer id and two datetime
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
class PdoUnitemporalDataTable extends PdoDataTable implements UnitemporalDataTable
{



    protected string $validFromColumn = self::DEFAULT_VALID_FROM_COLUMN;
    protected string $validUntilColumn = self::DEFAULT_VALID_UNTIL_COLUMN;

    /**
     *
     * @param PDO|PdoProvider $pdoOrProvider initialized PDO connection or provider
     * @param string $tableName SQL table name
     * @throws InvalidArgumentException
     */
    public function __construct(
        PDO|PdoProvider $pdoOrProvider,
        string $tableName,
        SqlDialect $sqlDialect,
        string $idColumnName = self::DEFAULT_ID_COLUMN_NAME,
        string $validFromColumnName = self::DEFAULT_VALID_FROM_COLUMN,
        string $validUntilColumnName = self::DEFAULT_VALID_UNTIL_COLUMN
    )
    {
        $this->validateTimeColumnName($validFromColumnName);
        $this->validateTimeColumnName($validUntilColumnName);
        $this->validFromColumn = $validFromColumnName;
        $this->validUntilColumn = $validUntilColumnName;
        parent::__construct($pdoOrProvider, $tableName, $sqlDialect, false, $idColumnName);

        // Check additional columns
        if (!$this->isTableColumnValid($this->validFromColumn, ['datetime'])) {
            // error message and code set by isTableColumnValid
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
        }

        if (!$this->isTableColumnValid($this->validUntilColumn, ['datetime'])) {
            // error message and code set by isTableColumnValid
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
        }

        $this->prepareRowExistsByIdStatement();
    }

    private function prepareRowExistsByIdStatement(): void
    {
        $quotedIdColumnName = $this->sqlDialect->quoteIdentifier($this->idColumnName);
        $quotedTableName = $this->sqlDialect->quoteIdentifier($this->tableName);
        $quotedValidUntilColumnName = $this->sqlDialect->quoteIdentifier($this->validUntilColumn);
        try {
            $this->statements['rowExistsById'] =
                $this->pdoProvider->getPdo()->prepare('SELECT ' . $quotedIdColumnName . ' FROM ' . $quotedTableName .
                    ' WHERE ' . $quotedIdColumnName . '= :id AND ' . $quotedValidUntilColumnName . '=' .
                    $this->quoteValue(TimeString::END_OF_TIMES));
        } catch (PDOException $e) { // @codeCoverageIgnore
            // @codeCoverageIgnoreStart
            $this->setError("Could not prepare statements "
                . "in constructor, " . $e->getMessage(), self::ERROR_PREPARING_STATEMENTS);
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode(), $e);
            // @codeCoverageIgnoreEnd
        }
    }

    public function getValidFromColumnName(): string
    {
        return $this->validFromColumn;
    }

    public function getValidUntilColumnName(): string
    {
        return $this->validUntilColumn;
    }

    public function setValidFromColumnName(string $validFromColumnName): void
    {
        $this->validateTimeColumnName($validFromColumnName);
        $this->validFromColumn = $validFromColumnName;
    }

    public function setValidUntilColumnName(string $validUntilColumnName): void
    {
        $this->validateTimeColumnName($validUntilColumnName);
        $this->validUntilColumn = $validUntilColumnName;
        if (isset($this->pdoProvider)) {
            $this->prepareRowExistsByIdStatement();
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateTimeColumnName(string $columnName): void
    {
        if ($columnName === '' || trim($columnName) !== $columnName || preg_match('/\s/', $columnName) === 1) {
            throw new InvalidArgumentException('Time column names must be non-empty and contain no whitespace');
        }
    }

    public function getConsistencyIssues(array|null $idsToCheck): array
    {
        if ($idsToCheck === null) {
            // check everything!
            $idsToCheck = $this->getUniqueIdsWithTime('');
        }
        $issues = [];
        foreach($idsToCheck as $id) {
            $rowHistory = $this->getRowHistory($id);
            $issues = [...$issues, ...UnitemporalConsistencyChecker::getConsistencyIssues($id, $rowHistory, $this->validFromColumn, $this->validUntilColumn)];
        }
        return $issues;
    }

    /**
     * @throws InvalidTimeStringException
     */
    private function getValidTimeString(string $timeString, string $context): string
    {
        try {
            $timeString = TimeString::fromString($timeString);
        } catch (InvalidTimeZoneException|MalformedStringException) {
            $this->throwExceptionForInvalidTime($timeString, $context);
        }
        return $timeString;
    }

    /**
     * Get all unique Ids in the table at the given time,
     * If the given time is not a valid timeString returns
     * all uniqueIds regardless of time.
     */
    public function getUniqueIdsWithTime(string $timeString): Iterator
    {
        $quotedValidFrom = $this->sqlDialect->quoteIdentifier($this->validFromColumn);
        $quotedValidUntil = $this->sqlDialect->quoteIdentifier($this->validUntilColumn);

        if ($timeString === '') {
            $sqlTimeConstraint = '';
        } else {
            try {
                $timeString = $this->getValidTimeString($timeString, 'getUniqueIdsWithTime');
                $quotedTimeString = $this->quoteValue($timeString);
                $sqlTimeConstraint = ' WHERE ' . $quotedValidFrom . '<=' . $quotedTimeString .
                    ' AND ' . $quotedValidUntil . '>' . $quotedTimeString;
            } catch (InvalidTimeStringException) { // @codeCoverageIgnore
                $sqlTimeConstraint = ''; // @codeCoverageIgnore
            }
        }

        $quotedTableName = $this->sqlDialect->quoteIdentifier($this->tableName);
        $quotedIdColumn = $this->sqlDialect->quoteIdentifier($this->idColumnName);

        $sql = "SELECT DISTINCT($quotedIdColumn) FROM $quotedTableName$sqlTimeConstraint ORDER BY $quotedTableName.$quotedIdColumn";

        $result = $this->doQuery($sql, "getUniqueIds");

        return new PdoUniqueIdsIterator($result);
    }


    #[Override]
    public function getUniqueIds(): Iterator
    {
        return $this->getUniqueIdsWithTime(TimeString::now());
    }


    /**
     * Creates a row valid from the current time.
     *
     * @throws InvalidTimeStringException
     */
    #[Override]
    public function realCreateRow(array $theRow): int
    {
        return $this->realCreateRowWithTime($theRow, TimeString::now());
    }

    /**
     * Creates a new row that is valid from the given time and returns the new
     * row's id
     *
     * @throws InvalidTimeStringException
     * @throws RowAlreadyExists
     */
    public function createRowWithTime(array $theRow, string $timeString): int
    {
        $this->resetError();
        $preparedRow = $this->getRowWithGoodIdForCreation($theRow);
        return $this->realCreateRowWithTime($preparedRow, $timeString);
    }

    /**
     * Actual creation of a row
     *
     * Uses PdoDataTable's realCreateRow to create a row since that method does
     * not check for already used Ids
     *
     * @throws InvalidTimeStringException
     */
    protected function realCreateRowWithTime(array $theRow, string $timeString): int
    {

        if ($timeString === '') {
            $timeString = TimeString::now(); // @codeCoverageIgnore
        } else {
            try {
                $timeString = TimeString::fromString($timeString);
            } catch (InvalidTimeZoneException|MalformedStringException) {
                $this->throwExceptionForInvalidTime($timeString, 'realCreateRowWithTime');
            }
        }

        $theRow[$this->validFromColumn] = $timeString;
        $theRow[$this->validUntilColumn] = TimeString::END_OF_TIMES;

        return parent::realCreateRow($theRow);
    }


    /**
     * Makes a row invalid from the given time
     *
     * @throws InvalidTimeStringException
     */
    protected function makeRowInvalid(array $theRow, string $timeString): int
    {
        try {
            $timeString = TimeString::fromString($timeString);
        } catch (InvalidTimeZoneException|MalformedStringException) {
            $this->throwExceptionForInvalidTime($timeString, 'makeRowInvalid');
        }
        $quotedTableName = $this->sqlDialect->quoteIdentifier($this->tableName);
        $quotedIdColumnName = $this->sqlDialect->quoteIdentifier($this->idColumnName);
        $quotedValidFrom = $this->sqlDialect->quoteIdentifier($this->validFromColumn);
        $quotedValidUntil = $this->sqlDialect->quoteIdentifier($this->validUntilColumn);
        $sql = 'UPDATE ' . $quotedTableName . ' SET ' .
            $quotedValidUntil . '=' . $this->quoteValue($timeString) .
            ' WHERE ' . $quotedIdColumnName . '=' . $theRow[$this->idColumnName] .
            ' AND ' . $quotedValidFrom . ' = ' . $this->quoteValue($theRow[$this->validFromColumn]) .
            ' AND ' . $quotedValidUntil . '= ' . $this->quoteValue($theRow[$this->validUntilColumn]);

        $this->doQuery($sql, 'makeRowInvalid');

        return $theRow[$this->idColumnName];
    }


    /**
     * @throws InvalidRowUpdateTime
     * @throws RowDoesNotExist
     */
    #[Override]
    public function realUpdateRow(array $theRow): void
    {
        try {
            $this->realUpdateRowWithTime($theRow, TimeString::now());
        } catch (InvalidTimeStringException) {
            // should never happen
        }

    }

    /**
     * Updates the last version of a row marking the change as
     * occurring at the given $timeString
     *
     * @throws InvalidRowUpdateTime
     * @throws InvalidTimeStringException
     * @throws RowDoesNotExist
     */
    public function realUpdateRowWithTime(array $theRow, string $timeString): void
    {

        $this->resetError();
        try {
            $timeString = TimeString::fromString($timeString);
        } catch (InvalidTimeZoneException|MalformedStringException) {
            $this->throwExceptionForInvalidTime($timeString, 'realUpdateRowWithTime');
        }
        $currentRow = $this->realGetRow($theRow[$this->idColumnName]);
        if ($currentRow === null) {
            $this->setError("Row does not exist", self::ERROR_ROW_DOES_NOT_EXIST);
            throw new RowDoesNotExist();
        }

        if ($currentRow[$this->validFromColumn] > $timeString) {
            // attempt to update a row before the row's last version is valid
            $this->setError("Row update time is before the row's last version", self::ERROR_INVALID_ROW_UPDATE_TIME);
            throw new InvalidRowUpdateTime();
        }


        $usingTransaction = false;

        if ($this->supportsTransactions() && !$this->isInTransaction()) {
            $usingTransaction = $this->startTransaction();
        }

        try {
            $this->makeRowInvalid($currentRow, $timeString);
            foreach (array_keys($currentRow) as $key) {
                if ($key === $this->validFromColumn || $key === $this->validUntilColumn) {
                    continue;
                }
                if (!array_key_exists($key, $theRow)) {
                    $theRow[$key] = $currentRow[$key];
                }
            }
            $this->realCreateRowWithTime($theRow, $timeString);
        } catch (Throwable $e) {
            if ($usingTransaction) {
                $errorCode = $this->getErrorCode();
                $errorMessage = $this->getErrorMessage();
                $this->rollBack();
                $this->setError($errorMessage, $errorCode);
            }
            throw new RuntimeException('realUpdateRowWithTime caught an unexpected exception', 0, $e);
        }

        if ($usingTransaction) {
            $result = $this->commit();
            if (!$result) {
                $this->setError("Error committing the update", self::ERROR_MYSQL_COULD_NOT_COMMIT); // @codeCoverageIgnore
            }
        }

    }


    /**
     * Returns the sql query needed to get the search results
     */
    #[Override]
    protected function getSearchSqlQuery(array $searchSpecArray, int $searchType, int $maxResults): string
    {

        $conditions = [];
        foreach ($searchSpecArray as $spec) {
            $conditions[] = $this->getSqlConditionFromSpec($spec);
        }
        $sqlLogicalOperator = 'AND';
        if ($searchType === self::SEARCH_OR) {
            $sqlLogicalOperator = 'OR';
        }
        $sql = 'SELECT * FROM ' . $this->sqlDialect->quoteIdentifier($this->tableName) . ' WHERE '
            . implode(' ' . $sqlLogicalOperator . ' ', $conditions);

        $eot = TimeString::END_OF_TIMES;

        $sql .= ' AND ' . $this->sqlDialect->quoteIdentifier($this->validUntilColumn) . '=' . $this->quoteValue($eot);

        if ($maxResults > 0) {
            $sql .= ' LIMIT ' . $maxResults;
        }

        return $sql;
    }

    #[Override]
    public function getAllRows(): ResultsIterator
    {
        try {
            return $this->getAllRowsWithTime(TimeString::now());

        } catch (InvalidTimeStringException) { // @codeCoverageIgnore
            throw new RuntimeException('getAllRows should never throw an exception'); // @codeCoverageIgnore
        }
    }


    /**
     * @throws InvalidTimeStringException
     */
    public function getAllRowsWithTime(string $timeString): ResultsIterator
    {
        $this->resetError();
        $timeString = $this->getValidTimeString($timeString, 'getAllRowsWithTime');
        $quotedTimeString = $this->quoteValue($timeString);
        $sql = 'SELECT * FROM ' . $this->sqlDialect->quoteIdentifier($this->tableName) .
            ' WHERE ' . $this->sqlDialect->quoteIdentifier($this->validFromColumn) . '<=' . $quotedTimeString .
            ' AND ' . $this->sqlDialect->quoteIdentifier($this->validUntilColumn) . '>' . $quotedTimeString;

        return new PdoResultsIterator($this->doQuery($sql, 'getAllRowsWithTime'), $this->idColumnName);
    }

    #[Override]
    public function getRow(int $rowId): ?array
    {
        $this->resetError();
        return $this->realGetRow($rowId, true);
    }

    public function realGetRow(int $rowId, bool $stripTimeInfo = false): ?array
    {

        try {
            $theRow = $this->getRowWithTime($rowId, TimeString::now());
        } catch (InvalidTimeStringException) { // @codeCoverageIgnore
            // impossible if TimeString::now() is working
            throw new RuntimeException('getRow should never throw an exception'); // @codeCoverageIgnore
        }
        if (!isset($theRow)) {
            return null;
        }
        if ($stripTimeInfo) {
            unset($theRow[$this->validFromColumn]);
            unset($theRow[$this->validUntilColumn]);
        }
        return $theRow;
    }


    public function getRowWithTime(int $rowId, string $timeString): ?array
    {
        $this->resetError();

        $timeString = $this->getValidTimeString($timeString, 'getRowWithTime');
        $quotedTimeString = $this->quoteValue($timeString);

        $sql = 'SELECT * FROM ' . $this->sqlDialect->quoteIdentifier($this->tableName) .
            ' WHERE ' . $this->sqlDialect->quoteIdentifier($this->idColumnName) . '=' . $rowId .
            ' AND ' . $this->sqlDialect->quoteIdentifier($this->validFromColumn) . '<=' . $quotedTimeString .
            ' AND ' . $this->sqlDialect->quoteIdentifier($this->validUntilColumn) . '>' . $quotedTimeString .
            ' LIMIT 1';

        $r = $this->doQuery($sql, 'getRowWithTime');

        $res = $r->fetch(PDO::FETCH_ASSOC);
        if ($res === false) {
            $this->setError('The row with id ' . $rowId . ' does not exist', self::ERROR_ROW_DOES_NOT_EXIST);
            return null;
        }
        $res[$this->idColumnName] = (int)$res[$this->idColumnName];
        return $res;
    }

    #[Override]
    public function findRows(array $rowToMatch, int $maxResults = 0): ResultsIterator
    {
        try {
            return $this->findRowsWithTime($rowToMatch, $maxResults, TimeString::now());
        } catch (InvalidTimeStringException) { // @codeCoverageIgnore
            // should never happen
            throw new RuntimeException('findRows should never throw an exception'); // @codeCoverageIgnore
        }
    }


    /**
     * @throws InvalidTimeStringException
     */
    public function findRowsWithTime($theRow, $maxResults, string $timeString): ResultsIterator
    {
        $this->resetError();

        $timeString = $this->getValidTimeString($timeString, 'findRowsWithTime');

        $keys = array_keys($theRow);
        $conditions = [];
        foreach ($keys as $key) {
            if ($key === $this->validFromColumn || $key === $this->validUntilColumn) {
                // Ignore time info keys
                continue;
            }
            $c = $this->sqlDialect->quoteIdentifier($key) . '=';
            if (is_string($theRow[$key])) {
                $c .= $this->pdoProvider->getPdo()->quote($theRow[$key]);
            } else {
                $c .= $theRow[$key];
            }
            $conditions[] = $c;
        }
        $quotedTimeString = $this->quoteValue($timeString);
        $sql = 'SELECT * FROM ' . $this->sqlDialect->quoteIdentifier($this->tableName) . ' WHERE ' .
            implode(' AND ', $conditions) .
            ' AND ' . $this->sqlDialect->quoteIdentifier($this->validFromColumn) . '<=' . $quotedTimeString .
            ' AND ' . $this->sqlDialect->quoteIdentifier($this->validUntilColumn) . '>' . $quotedTimeString;

        if ($maxResults > 0) {
            $sql .= ' LIMIT ' . $maxResults;
        }

        try {
            $r = $this->pdoProvider->getPdo()->query($sql);
        } catch (PDOException $e) {
            if ($this->sqlDialect->isSearchErrorRecoverable($e)) {
                // The exception was thrown because of an SQL syntax error, but
                // this should only happen when one of the keys does not exist or
                // is of the wrong type. This just means that the search
                // did not have any results, so let's set the error code
                // to be 'empty result set'
                // However, just in case this may be hiding something else,
                // let's report everything in the error message
                $this->setError('Query error in realFindRowsWithTime (reported as no results) : ' .
                    $e->getMessage() . ' :: query = ' . $sql, self::ERROR_EMPTY_RESULT_SET);
                return new ArrayResultsIterator([]);
            }
            // @codeCoverageIgnoreStart
            $this->setError('Query error in realFindRowsWithTime: ' . $e->getMessage() . ' :: query = ' . $sql,
                self::ERROR_MYSQL_QUERY_ERROR);
            return new ArrayResultsIterator([]);
            // @codeCoverageIgnoreEnd
        }
        if ($r === false) {
            // @codeCoverageIgnoreStart
            $this->setError('Unknown error in realFindRowsWithTime when executing query: ' . $sql,
                self::ERROR_UNKNOWN_ERROR);
            return new ArrayResultsIterator([]);
            // @codeCoverageIgnoreEnd
        }
        return new PdoResultsIterator($r, $this->idColumnName);
    }


    #[Override]
    public function deleteRow(int $rowId): int
    {
        try {
            return $this->deleteRowWithTime($rowId, TimeString::now());
        } catch (InvalidTimeStringException|InvalidRowUpdateTime $e) { // @codeCoverageIgnore
            throw new RuntimeException('Unexpected error in deleteRow', $e->getCode(), $e); // @codeCoverageIgnore
        }
    }


    /**
     * 'Deletes' a row by making its current version invalid as of the given time.
     *
     * It can only delete the latest valid version of the row, not previous ones.
     *
     * Returns 1 if the row was deleted, 0 if the row did not exist in the first place.
     *
     * @throws InvalidTimeStringException
     * @throws InvalidRowUpdateTime
     */
    public function deleteRowWithTime(int $rowId, string $timeString): int
    {
        $timeString = $this->getValidTimeString($timeString, 'deleteRowWithTime');
        $oldRow = $this->realGetRow($rowId);
        if ($oldRow === null) {
            return 0;
        }
        if ($timeString <= $oldRow[$this->validFromColumn]) {
            $this->setError('The given time is not later than the last version of the row', self::ERROR_INVALID_ROW_UPDATE_TIME);
            throw new InvalidRowUpdateTime("The given time is not later than the last version of the row");
        }
        $this->makeRowInvalid($oldRow, $timeString);
        return 1;
    }


    /**
     * @throws InvalidTimeStringException
     */
    private function throwExceptionForInvalidTime(string $timeString, string $context): void
    {
        $this->setError("Invalid time given for $context : \"$timeString\"", self::ERROR_INVALID_TIME);
        throw new InvalidTimeStringException($this->getErrorMessage(), $this->getErrorCode());
    }


    /**
     * @throws InvalidTimeStringException
     */
    public function rowExistsWithTime(int $rowId, string $timeString): bool
    {
        // TODO: Is there a more efficient way to to this?
        return $this->getRowWithTime($rowId, $timeString) !== null;

    }

    public function searchWithTime(array $searchSpecArray, int $searchType, string $timeString, int $maxResults = 0): ResultsIterator
    {
        $this->checkSpec($searchSpecArray, $searchType);
        try {
            $timeString = TimeString::fromString($timeString);
        } catch (InvalidTimeZoneException|MalformedStringException) {
            $this->throwExceptionForInvalidTime($timeString, 'searchWithTime');
        }

        $conditions = [];
        foreach ($searchSpecArray as $spec) {
            $conditions[] = $this->getSqlConditionFromSpec($spec);
        }
        $sqlLogicalOperator = $searchType === self::SEARCH_OR ? ' OR ' : ' AND ';
        $quotedTimeString = $this->quoteValue($timeString);
        $sql = 'SELECT * FROM ' . $this->sqlDialect->quoteIdentifier($this->tableName) . ' WHERE (' .
            implode($sqlLogicalOperator, $conditions) . ')' .
            ' AND ' . $this->sqlDialect->quoteIdentifier($this->validFromColumn) . '<=' . $quotedTimeString .
            ' AND ' . $this->sqlDialect->quoteIdentifier($this->validUntilColumn) . '>' . $quotedTimeString;

        if ($maxResults > 0) {
            $sql .= ' LIMIT ' . $maxResults;
        }

        return new PdoResultsIterator($this->doQuery($sql, 'searchWithTime'), $this->idColumnName);

    }

    #[Override]
    public function search(array $searchSpecArray, int $searchType = self::SEARCH_AND, int $maxResults = 0): ResultsIterator
    {
        try {
            return $this->searchWithTime($searchSpecArray, $searchType, TimeString::now(), $maxResults);
        } catch (InvalidTimeStringException $e) {
            throw new RuntimeException("Unexpected error: " . $e->getMessage(), $e->getCode(), $e);
        }
    }


    public function updateRowWithTime(array $theRow, string $timeString): void
    {
        $this->resetError();
        if (!$this->isRowIdGoodForRowUpdate($theRow, 'PdoUnitemporalDataTable updateRowWithTime')) {
            throw new InvalidRowForUpdate($this->getErrorMessage(), $this->getErrorCode());
        }
        $this->realUpdateRowWithTime($theRow, $timeString);
    }

    /**
     * Returns an array with all the different versions of the row with the given $rowId
     * Each version has the same fields as any row in the datatable plus
     *  'valid_from' and a 'valid_until' fields.
     *
     * @throws RowDoesNotExist
     */
    public function getRowHistory(int $rowId): array
    {
        $sql = 'SELECT * FROM ' . $this->sqlDialect->quoteIdentifier($this->tableName) .
            ' WHERE ' . $this->sqlDialect->quoteIdentifier($this->idColumnName) . '=' . $rowId .
            ' ORDER BY ' . $this->sqlDialect->quoteIdentifier($this->validFromColumn);

        $r = $this->doQuery($sql, 'getRowHistory');
        if ($r->rowCount() === 0) {
            $this->setError('The row with id ' . $rowId . ' has never existed', self::ERROR_ROW_DOES_NOT_EXIST);
            throw new RowDoesNotExist($this->getErrorMessage(), $this->getErrorCode());
        }

        $rows = $r->fetchAll(PDO::FETCH_ASSOC);

        return $this->forceIntIds($rows);
    }
}
