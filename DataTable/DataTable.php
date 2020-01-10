<?php

/*
 * The MIT License
 *
 * Copyright 2017-20 Rafael Nájera <rafael@najera.ca>.
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

use InvalidArgumentException;
use RuntimeException;

interface DataTable
{

    /**
     * Returns true if the row with the given Id exists
     *
     * @param int $rowId
     * @return bool
     */
    public function rowExists(int $rowId) : bool;

    /**
     * Creates a new row in the table.
     *
     * If the given row array does not have a value for 'id' or if the value
     * is equal to 0 a new id will be assigned.
     *
     * Otherwise, if the given Id is not an int or if the id
     * already exists in the table the function will throw
     * an exception
     *
     * @param array $theRow
     * @return int the Id of the newly created row
     * @throws RuntimeException
     */
    public function createRow(array $theRow) : int;


    /**
     * Gets the row with the given row Id.
     * If the row does not exist throws an InvalidArgument exception
     *
     * @param int $rowId
     * @return array
     * @throws InvalidArgumentException
     */
    public function getRow(int $rowId) : array;

    /**
     * Gets all rows in the table
     *
     * @return array
     */
    public function getAllRows() : array;


    /**
     * Deletes the row with the given Id.
     *
     * Returns the number of rows actually deleted without problems, which should be 1 if
     * the row the given Id existed in the datable, or 0 if there was no such row in
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
     * if $maxResults > 0, an array of max $maxResults will be returned
     * if $maxResults <= 0, all results will be returned
     *
     * @param array $rowToMatch
     * @param int $maxResults
     * @return array
     */
    function findRows(array $rowToMatch, int $maxResults = 0) : array;


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
     * if $maxResults > 0, an array of max $maxResults will be returned
     * if $maxResults <= 0, all results will be returned
     *
     * @param array $searchSpecArray
     * @param int $searchType
     * @param int $maxResults
     * @return array
     */
    public function search(array $searchSpecArray, int $searchType = self::SEARCH_AND, int $maxResults = 0) : array;

    /**
     * Updates the table with the given row, which must contain an 'id'
     * field specifying the row to update.
     *
     * If the given row does not contain a valid 'id' field, or if the Id
     * is valid but there is no row with that id the table, an InvalidArgument exception
     * will be thrown.
     *
     * Only the keys given in $theRow are updated. The user must make sure
     * that not updating the non-given keys does not cause any problem
     *  (e.g., if in an SQL implementation the underlying SQL table does not
     *  have default values for the non-given keys)
     *
     * If the row was not successfully updated, throws a Runtime exception
     *
     * @param array $theRow
     * @return void
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function updateRow(array $theRow) : void;


    /**
     * Returns the id of one row in which $row[$key] === $value
     * or false if such a row cannot be found or an error occurred whilst
     * trying to find it.
     *
     * @param string $key
     * @param mixed $value
     * @return int
     */
    public function getIdForKeyValue(string $key, $value) : int;

    /**
     * Returns the max value in the given column.
     *
     * The actual column must exist and be numeric for the actual value returned
     * to be meaningful. Implementations may choose to throw a RunTime exception
     * in this case.
     *
     * @param string $columnName
     * @return int
     */
    public function getMaxValueInColumn(string $columnName) : int;


    /**
     * @return int the max id in the table
     */
    public function getMaxId() : int;

}