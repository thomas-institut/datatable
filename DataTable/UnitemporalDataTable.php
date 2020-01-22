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

/**
 * Defines a class that provides the same methods as a DataTable but with a
 * time indication.
 *
 * Just as with a regular DataTable, the table can be understood as being composed of rows, each one with
 * a unique Id. A unitemporal datatable, however, has access to different versions of each row, so that
 * it is possible to retrieve a version of a row at any particular moment in time.
 *
 * This is a transitional interface. In a later version of DataTable it will become
 * an abstract class to implement Unitemporal DataTables with any DataTable foundation
 *
 * @package DataTable
 */
interface UnitemporalDataTable extends DataTable
{


    /**
     * Creates a row that exists starting from the given time
     * Returns the id of the newly created row.
     *
     * @param array $theRow
     * @param string $timeString
     * @return int
     */
    public function createRowWithTime(array $theRow, string $timeString) : int;

    /**
     * Returns true if the row with the given $rowId exists at the given time
     *
     * @param int $rowId
     * @param string $timeString
     * @return bool
     */
    public function rowExistsWithTime(int $rowId, string $timeString) : bool;

    /**
     * Gets the version of the row with the given $rowId at the given time.
     *
     * @param int $rowId
     * @param string $timeString
     * @return array
     */
    public function getRowWithTime(int $rowId, string $timeString) : array;

    /**
     * Returns an array of versions of rows that match the key/value pairs in the given $theRow
     * at the given time
     *
     * @param $theRow
     * @param $maxResults
     * @param string $timeString
     * @return array
     */
    public function findRowsWithTime($theRow, $maxResults, string $timeString) : array;

    /**
     * Searches the datatable the versions of rows that match the given $searchSpec array and $searchType
     * at the given time
     *
     * @param array $searchSpecArray
     * @param int $searchType
     * @param string $timeString
     * @param int $maxResults
     * @return array
     */
    public function searchWithTime(array $searchSpecArray, int $searchType, string $timeString, int $maxResults = 0): array;

    /**
     * Creates a new version of the given row that is valid from the given time
     * Assumes that the given time is later than the last version of the row.
     *
     * @param array $theRow
     * @param string $timeString
     */
    public function updateRowWithTime(array $theRow, string $timeString) : void;

    /**
     * Makes a row non-existent after the given time.
     *
     * It does not delete any previous version of the row. It simply makes the last version of the row
     * be invalid after the given time.
     *
     * @param int $rowId
     * @param string $timeString
     * @return int
     */
    public function deleteRowWithTime(int $rowId, string $timeString) : int;

    /**
     * Returns an array with all the different versions of the row with the given $rowId
     * Each version has the same fields as any row in the datatable plus
     *  'valid_from' and a 'valid_until' fields.
     *
     * @param int $rowId
     * @return array
     */
    public function getRowHistory(int $rowId) : array;

}