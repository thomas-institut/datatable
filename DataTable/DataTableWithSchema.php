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
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\DataTable\Exception\InvalidRow;
use ThomasInstitut\DataTable\Exception\InvalidSearchSpec;
use ThomasInstitut\DataTable\Exception\InvalidSearchType;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\IdGenerator\IdGenerator;
use ThomasInstitut\DataTable\ResultsIterator\ResultsIterator;
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\SupportedSearchCondition;

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
 *
 * @extends ArrayAccess<int, array<string, mixed>>
 * @extends IteratorAggregate<int, array<string, mixed>>
 */
interface DataTableWithSchema extends ArrayAccess, IteratorAggregate, LoggerAwareInterface
{
    const array MandatorySupportedDataTypes = [
        ColumnDataType::Id,
        ColumnDataType::Text,
        ColumnDataType::Integer,
        ColumnDataType::Boolean,
    ];

    /**
     * Returns a list of the data types supported by this DataTable.
     *
     * All implementations must support at least the data types Id, Text, Integer and Boolean.
     *
     * @return array<ColumnDataType>
     */
    public function getSupportedDataTypes(): array;
    /**
     * Assigns an IdGenerator to the DataTable
     *
     * @param IdGenerator $ig
     * @return void
     */
    public function setIdGenerator(IdGenerator $ig): void;

    /**
     * Returns true if the row with the given ID exists
     *
     * @param int $rowId
     * @return bool
     */
    public function rowExists(int $rowId): bool;

    /**
     * Creates a new row in the table.
     *
     * If the given row array does not have a value for the DataTable's ID column, that value is not an integer, or the
     * value is less or equal to zero, a new unique ID will be assigned.
     *
     * Otherwise, if the given ID already exists in the table, the function will throw an exception.
     *
     * @param array<string, mixed> $theRow
     * @return int
     * @throws RowAlreadyExists
     * @throws InvalidRow
     */
    public function createRow(array $theRow): int;


    /**
     * Returns the row with the given row ID.
     *
     * If the row does not exist, returns null
     *
     * @param int $rowId
     * @return array<string, mixed>|null
     */
    public function getRow(int $rowId): ?array;

    /**
     * Returns an iterator with all rows in the table
     *
     * @return ResultsIterator
     */
    public function getAllRows(): ResultsIterator;

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
    public function deleteRow(int $rowId): int;

    /**
     * Finds rows in the data table that match the values in $rowToMatch
     *
     * A row in the data table matches $rowToMatch if for every field
     * in $rowToMatch the row has exactly that same value.
     *
     * If $maxResults > 0, an iterator of max $maxResults will be returned;
     * if $maxResults <= 0, all results will be returned
     *
     * @param array<string, mixed> $rowToMatch
     * @param int $maxResults
     * @return ResultsIterator
     * @throws InvalidRow
     */
    function findRows(array $rowToMatch, int $maxResults = 0): ResultsIterator;


    /**
     * Searches the datatable according to the given $searchSpecArray
     *
     * $searchSpecArray is an array of searchSpecs.
     *
     * If $searchType is SEARCH_AND, the row must satisfy:
     *      $searchSpec[0] && $searchSpec[1] && ...  && $searchSpec[n]
     *
     * If  $searchType is SEARCH_OR, the row must satisfy the negation of the spec:
     *
     *      $searchSpec[0] || $searchSpec[1] || ...  || $searchSpec[n]
     *
     *
     * A searchSpec is a class with the following properties:
     *      $searchSpec->column  // column to which the condition applies
     *      $searchSpec->condition // a SearchCondition
     *      $searchSpec->value // the value to which the condition applies
     *
     * Notice that each condition type has a negation:
     *      EQUAL_TO  <==> NOT_EQUAL_TO
     *      LESS_THAN  <==>  GREATER_OR_EQUAL_TO
     *      LESS_OR_EQUAL_TO <==> GREATER_THAN
     *
     * If $maxResults > 0, an iterator of max $maxResults will be returned;
     * if $maxResults <= 0, an iterator with all results will be returned
     *
     * @param array<SearchSpec> $searchSpecArray
     * @param SearchType $searchType
     * @param int $maxResults
     * @return ResultsIterator
     * @throws InvalidSearchSpec
     * @throws InvalidSearchType
     * @throws InvalidRow
     */
    public function search(array $searchSpecArray, SearchType $searchType = SearchType::And, int $maxResults = 0): ResultsIterator;


    /**
     * Returns an array of SupportedSearchCondition objects, each of which
     * contains the supported search conditions for a given column data type.
     *
     * If a type is not present in the array, it means that no search conditions are supported for that type.
     *
     * An empty array means that search is not supported.
     *
     * This array describes the search capabilities of the table. If a search is done using a non-supported
     * condition, an InvalidSearchSpec exception will be thrown.
     *
     * @return array<SupportedSearchCondition>
     */
    public function getSupportedSearchConditions(): array;


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
     * @param array<string, mixed> $theRow
     * @return void
     * @throws InvalidRow
     */
    public function updateRow(array $theRow): void;


    /**
     * Returns true if SQL-like transactions are supported.
     *
     * If transactions are supported, any updates to the table (row creation/update/delete) after
     * a call to startTransaction() will not take effect until commit() is called.
     *
     * If transactions are not supported, startTransaction() and commit() will do nothing.
     *
     * @return bool
     */
    public function supportsTransactions(): bool;


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
    public function startTransaction(): bool;

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
    public function rollBack(): bool;


    /**
     * Returns true if a transaction initiated by the table is currently going on.
     *
     * Always returns false if the DataTable does not support transactions.
     *
     * @return bool
     */
    public function isInTransaction(): bool;


    /**
     * Attempts to determine if a transaction is currently going on in the underlying
     * database.
     *
     * Always returns false if the DataTable does not support transactions.
     *
     * @return bool
     */
    public function isUnderlyingDatabaseInTransaction(): bool;


    /**
     * Returns the max value in the given column.
     *
     * The actual column must exist and be numeric for the actual value returned
     * to be meaningful. Implementations may throw a RunTime exception
     * if the column in the underlying database is not numeric.
     *
     * @param string $columnName
     * @return int
     * @throws InvalidArgumentException
     */
    public function getMaxValueInColumn(string $columnName): int;

    /**
     * Returns the max id in the table
     *
     * @return int
     */
    public function getMaxId(): int;

    /**
     * Returns an iterator with all the unique row ids in the table in ascending order.
     *
     * @return Iterator
     */
    public function getUniqueIds(): Iterator;


    /**
     * Returns the table's name
     *
     * @return string
     */
    public function getName(): string;

}