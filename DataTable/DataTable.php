<?php

/*
 * The MIT License
 *
 * Copyright 2017-24 Thomas-Institut, Universität zu Köln.
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

use ArrayAccess;
use Iterator;
use IteratorAggregate;
use Psr\Log\LoggerAwareInterface;

/**
 * An interface to a table made out of associative array rows addressable by a unique integer and that is normally
 * stored in a database table.
 *
 * It captures common functionality for this kind of table but does not attempt to impose a particular implementation.
 * The idea is that one descendant of this class will implement the table as an SQL table, but an implementation
 * with arrays or with something just as simple can be provided for testing.
 *
 * Each row must have a unique id integer column or key, which by default has the name 'id'. This name can be
 * changed with setIdColumnName.
 *
 * The assignment of IDs is by default left to the class, not to the underlying database, but implementations can
 * change this behaviour.
 *
 * @see https://github.com/thomas-institut/datatable
 *
 * @author Rafael Nájera <rafael.najera@uni-koeln.de>
 */
interface DataTable extends ArrayAccess, IteratorAggregate, LoggerAwareInterface, ErrorReporter
{
    const NULL_ROW_ID = -1;

    const DEFAULT_ID_COLUMN_NAME = 'id';

    const SEARCH_AND = 0;
    const SEARCH_OR = 1;

    /**
     * Search spec fields
     */

    const SEARCH_SPEC_COLUMN = 'column';
    const SEARCH_SPEC_CONDITION = 'condition';
    const SEARCH_SPEC_VALUE = 'value';

    /**
     * Search condition types
     */

    const COND_EQUAL_TO = 0;
    const COND_NOT_EQUAL_TO = 1;
    const COND_LESS_THAN = 2;
    const COND_LESS_OR_EQUAL_TO = 3;
    const COND_GREATER_THAN = 4;
    const COND_GREATER_OR_EQUAL_TO = 5;

    /**
     * Error code constants
     */
    const ERROR_NO_ERROR = 0;
    const ERROR_UNKNOWN_ERROR = 1;
    const ERROR_ROW_DOES_NOT_EXIST = 102;
    const ERROR_ROW_ALREADY_EXISTS = 103;
    const ERROR_ID_NOT_INTEGER = 104;
    const ERROR_ID_NOT_SET = 105;
    const ERROR_ID_IS_ZERO = 106;
    const ERROR_EMPTY_RESULT_SET = 107;
    const ERROR_KEY_VALUE_NOT_FOUND = 108;
    const ERROR_INVALID_SEARCH_TYPE = 109;

    const ERROR_INVALID_SPEC_ARRAY = 110;
    const ERROR_SPEC_ARRAY_IS_EMPTY = 111;
    const ERROR_SPEC_INVALID_COLUMN = 112;
    const ERROR_SPEC_NO_VALUE = 113;
    const ERROR_SPEC_INVALID_CONDITION = 114;

    const ERROR_TRANSACTIONS_NOT_SUPPORTED = 115;
    const ERROR_NOT_IMPLEMENTED = 116;

    /**
     * Assigns an IdGenerator to the DataTable
     *
     * @param IdGenerator $ig
     * @return void
     */
    public function setIdGenerator(IdGenerator $ig) : void;

    /**
     * Returns true if the row with the given ID exists
     *
     * @param int $rowId
     * @return bool
     */
    public function rowExists(int $rowId) : bool;

    /**
     * Creates a new row in the table.
     *
     * If the given row array does not have a value for the DataTable's ID column, that value is not an integer, or the
     * value is less or equal to zero a new unique ID will be assigned.
     *
     * Otherwise, if the given ID already exists in the table the function will throw an exception.
     *
     * @param array $theRow
     * @return int
     * @throws RowAlreadyExists
     */
    public function createRow(array $theRow) : int;


    /**
     * Returns the row with the given row ID.
     *
     * If the row does not exist returns null
     *
     * @param int $rowId
     * @return array|null
     */
    public function getRow(int $rowId) : ?array;

    /**
     * Returns an iterator with all rows in the table
     *
     * @return DataTableResultsIterator
     */
    public function getAllRows() : DataTableResultsIterator;

    /**
     * Deletes the row with the given ID.
     *
     * Returns the number of rows actually deleted without problems, which should be 1 if
     * the row the given ID existed in the datable, or 0 if there was no such row in
     * the first place.
     *
     * @param int $rowId
     * @return int
     */
    public function deleteRow(int $rowId) : int;

    /**
     * Finds rows in the data table that match the values in $rowToMatch
     *
     * A row in the data table matches $rowToMatch if for every field
     * in $rowToMatch the row has exactly that same value.
     *
     * if $maxResults > 0, an iterator of max $maxResults will be returned
     * if $maxResults <= 0, all results will be returned
     *
     * @param array $rowToMatch
     * @param int $maxResults
     * @return DataTableResultsIterator
     */
    function findRows(array $rowToMatch, int $maxResults = 0) : DataTableResultsIterator;


    /**
     * Searches the datatable according to the given $searchSpec
     *
     * $searchSpec is an array of conditions.
     *
     * If $searchType is SEARCH_AND, the row must satisfy:
     *      $searchSpec[0] && $searchSpec[1] && ...  && $searchSpec[n]
     *
     * if  $searchType is SEARCH_OR, the row must satisfy the negation of the spec:
     *
     *      $searchSpec[0] || $searchSpec[1] || ...  || $searchSpec[n]
     *
     *
     * A condition is an array of the form:
     *
     *  $condition = [
     *      'column' => 'columnName',
     *      'condition' => one of (EQUAL_TO, NOT_EQUAL_TO, LESS_THAN, LESS_OR_EQUAL_TO, GREATER_THAN, GREATER_OR_EQUAL_TO)
     *      'value' => someValue
     * ]
     *
     * Notice that each condition type has a negation:
     *      EQUAL_TO  <==> NOT_EQUAL_TO
     *      LESS_THAN  <==>  GREATER_OR_EQUAL_TO
     *      LESS_OR_EQUAL_TO <==> GREATER_THAN
     *
     * if $maxResults > 0, an iterator of max $maxResults will be returned
     * if $maxResults <= 0, an iterator with all results will be returned
     *
     * @param array $searchSpecArray
     * @param int $searchType
     * @param int $maxResults
     * @return DataTableResultsIterator
     * @throws InvalidSearchSpec
     * @throws InvalidSearchType
     */
    public function search(array $searchSpecArray, int $searchType = self::SEARCH_AND, int $maxResults = 0) : DataTableResultsIterator;

    /**
     * Updates the table with the given row, which must contain an id
     * field matching the current idColumnName specifying the row to update.
     *
     * If the given row does not contain a valid id field, or if the id
     * is valid but there is no row with that id the table, an InvalidRowForUpdate exception
     * will be thrown.
     *
     * Only the keys given in $theRow are updated. The user must make sure
     * that not updating the non-given keys does not cause any problem
     *  (e.g., if in an SQL implementation the underlying SQL table does not
     *  have default values for the non-given keys)
     *
     *
     * @param array $theRow
     * @return void
     * @throws InvalidRowForUpdate
     */
    public function updateRow(array $theRow) : void;


    /**
     * Returns true if SQL-like transactions are supported.
     *
     * If transactions are supported, any updates to the table (row creation/update/delete) after
     * a call to startTransaction() will not take effect until commit() is called.
     *
     * If transaction are not supported startTransaction() and commit() will do nothing.
     *
     * @return bool
     */
    public function supportsTransactions() : bool;


    /**
     *
     * If transactions are supported, all further changes to be table will not take effect until commit() is called
     * and changes can be cancelled with rollBack()
     *
     * Returns true if the transaction started successfully.
     *
     * If transactions are not supported, returns false.
     *
     * @return bool
     */
    public function startTransaction() : bool;

    /**
     * If transactions are supported, commits all changes since the last call to startTransaction()
     *
     * Returns true if the commit was successful.
     *
     * If transactions are not supported, returns false.
     *
     * @return bool
     */
    public function commit(): bool;


    /**
     * If transactions are supported, rolls back all changes since the last call to startTransaction()
     * and ends the transaction.
     *
     * Returns true if the rollBack was successful.
     *
     * If transactions are not supported, returns false.
     *
     * @return bool
     */
    public function rollBack() : bool;


    /**
     * Returns true if a transaction initiated by the table is currently going on.
     *
     * Always returns false if the DataTable does not support transactions.
     *
     * @return bool
     */
    public function isInTransaction() : bool;


    /**
     * Attempts to determine if a transaction is currently going on in the underlying
     * database.
     *
     * Always returns false if the DataTable does not support transactions.
     *
     * @return bool
     */
    public function isUnderlyingDatabaseInTransaction() : bool;

    /**
     * Returns the id of one row in which $row[$key] === $value
     * or -1 if no such row was found
     *
     * @param string $key
     * @param mixed $value
     * @return int
     * @deprecated Use normal search functions
     */
    public function getIdForKeyValue(string $key, mixed $value) : int;

    /**
     * Returns the max value in the given column.
     *
     * The actual column must exist and be numeric for the actual value returned
     * to be meaningful. Implementations may throw a RunTime exception
     * if the column in the underlying database is not numeric.
     *
     * @param string $columnName
     * @return int
     */
    public function getMaxValueInColumn(string $columnName) : int;

    /**
     * Returns the max id in the table
     *
     * @return int
     */
    public function getMaxId() : int;

    /**
     * Returns an iterator with all the unique row ids in the table in ascending order.
     *
     * @return Iterator
     */
    public function getUniqueIds() : Iterator;


    /**
     * Returns the table's name, which may be an empty string if the name is not set.
     *
     * @return string
     */
    public function getName() : string;

    /**
     * Sets the table's name.
     *
     * @param string $name
     * @return void
     */
    public function setName(string $name) : void;

    /**
     * Sets the name of the id column in the table to reflect the actual name used in the underlying database table.
     * Normally, this will be called right after constructing the DataTable object.
     *
     * This method does not change anything in the underlying database.
     *
     * @param string $columnName
     * @return void
     */
    public function setIdColumnName(string $columnName) : void;


    /**
     * Returns the current id column name used in the DataTable object.
     * @return string
     */
    public function getIdColumnName() : string;

}