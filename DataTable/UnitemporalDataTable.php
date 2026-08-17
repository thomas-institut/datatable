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

use ThomasInstitut\DataTable\Exception\InvalidRowForUpdate;
use ThomasInstitut\DataTable\Exception\InvalidRowUpdateTime;
use ThomasInstitut\DataTable\Exception\InvalidSearchSpec;
use ThomasInstitut\DataTable\Exception\InvalidSearchType;
use ThomasInstitut\DataTable\Exception\InvalidTimeStringException;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\Exception\RowDoesNotExist;
use ThomasInstitut\DataTable\ResultsIterator\ResultsIterator;

/**
 * Defines a class that provides the same methods as a DataTable but with a
 * time indication.
 *
 * The term 'unitemporal' is taken from Johnston and Weis, *Managing Time in Relational Databases*, 2010
 *
 * The normal DataTable methods for creating, updating and deleting
 * rows do not delete any previous data but just mark that data as
 * not valid anymore. The normal DataTable rows as complemented with valid_from and valid_until columns
 * that hold the time information. The id column is therefore not unique in the table since it is reused
 * to identify different versions of the same row.
 *
 * Data retrieval methods (getRow and findRows) get
 * the latest versions of the data and strip out the time information, so,
 * if used with the normal methods, the class behaves as any other DataTable.
 * There are, however, new methods to retrieve data at previous points in time.
 *
 *
 *
 */
interface UnitemporalDataTable extends DataTable
{

    const int ERROR_INVALID_ROW_UPDATE_TIME = 2001;
    const int ERROR_INVALID_TIME = 2002;


    const string DEFAULT_VALID_FROM_COLUMN = 'valid_from';
    const string DEFAULT_VALID_UNTIL_COLUMN = 'valid_until';

    /**
     * Creates a row that exists starting from the given time
     * Returns the id of the newly created row.
     *
     * @param array $theRow
     * @param string $timeString
     * @return int
     * @throws InvalidTimeStringException
     * @throws RowAlreadyExists
     */
    public function createRowWithTime(array $theRow, string $timeString): int;

    /**
     * Returns true if the row with the given $rowId exists at the given time
     *
     * @param int $rowId
     * @param string $timeString
     * @return bool
     * @throws InvalidTimeStringException
     */
    public function rowExistsWithTime(int $rowId, string $timeString): bool;

    /**
     * Gets the version of the row with the given $rowId at the given time.
     * If the row does not exist at the given time, returns null.
     *
     * @param int $rowId
     * @param string $timeString
     * @return array|null
     * @throws InvalidTimeStringException
     */
    public function getRowWithTime(int $rowId, string $timeString): ?array;

    /**
     * Returns an iterator with versions of rows that match the key/value pairs in the given $theRow
     * at the given time
     *
     * @param $theRow
     * @param $maxResults
     * @param string $timeString
     * @return ResultsIterator
     * @throws InvalidTimeStringException
     */
    public function findRowsWithTime($theRow, $maxResults, string $timeString): ResultsIterator;

    /**
     * Searches the datatable for rows that match the given $searchSpec array and $searchType
     * at the given time
     *
     * @param array $searchSpecArray
     * @param int $searchType
     * @param string $timeString
     * @param int $maxResults
     * @return ResultsIterator
     * @throws InvalidSearchSpec
     * @throws InvalidSearchType
     * @throws InvalidTimeStringException
     */
    public function searchWithTime(array $searchSpecArray, int $searchType, string $timeString, int $maxResults = 0): ResultsIterator;

    /**
     * Creates a new version of the given row that is valid from the given time.
     *
     * Assumes that the given time is later than the last version of the row.
     *
     * @param array $theRow
     * @param string $timeString
     * @throws InvalidTimeStringException
     * @throws RowDoesNotExist
     * @throws InvalidRowForUpdate
     * @throws InvalidRowUpdateTime
     */
    public function updateRowWithTime(array $theRow, string $timeString): void;

    /**
     * Makes a row non-existent after the given time.
     *
     * It does not delete any previous version of the row. It simply makes the last version of the row
     * be invalid after the given time.
     *
     * @param int $rowId
     * @param string $timeString
     * @return int
     * @throws InvalidTimeStringException
     * @throws InvalidRowUpdateTime -- if the given time is not later than the last version of the row
     */
    public function deleteRowWithTime(int $rowId, string $timeString): int;

    /**
     * Returns an array with all the different versions of the row with the given $rowId in ascending chronological order.
     *
     * Be aware that this method will return rows even if the given $rowId is not valid now. Only if $rowId has
     * never existed will this method throw a RowDoesNotExist exception.
     *
     * @param int $rowId
     * @return array
     * @throws RowDoesNotExist
     */
    public function getRowHistory(int $rowId): array;

}