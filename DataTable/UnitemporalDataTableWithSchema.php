<?php

declare(strict_types=1);

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

use ThomasInstitut\DataTable\Exception\InvalidRow;
use ThomasInstitut\DataTable\Exception\InvalidRowUpdateTime;
use ThomasInstitut\DataTable\Exception\InvalidSearchSpec;
use ThomasInstitut\DataTable\Exception\InvalidSearchType;
use ThomasInstitut\DataTable\Exception\InvalidTimeStringException;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\Exception\RowDoesNotExist;
use ThomasInstitut\DataTable\ResultsIterator\ResultsIterator;
use ThomasInstitut\DataTable\Schema\ColumnDataType;

/**
 * Defines a class that provides the same methods as a DataTable but with a
 * time indication.
 *
 * Just as with a regular DataTable, the table can be understood as being composed of rows, each one with
 * a unique ID. An unitemporal datatable, however, has access to different versions of each row, so that
 * it is possible to retrieve a version of a row at any particular moment in time.
 *
 *
 */
interface UnitemporalDataTableWithSchema extends DataTableWithSchema
{


    const array AdditionalRequiredDataTypes = [ColumnDataType::TimeString, ColumnDataType::ValidUntil, ColumnDataType::ValidFrom];
    /**
     * Creates a row that exists starting from the given time
     * Returns the id of the newly created row.
     *
     * @param array<string, mixed> $theRow
     * @throws InvalidTimeStringException
     * @throws RowAlreadyExists
     * @throws InvalidRow
     */
    public function createRowWithTime(array $theRow, string $timeString) : int;

    /**
     * Returns true if the row with the given $rowId exists at the given time
     * @throws InvalidTimeStringException
     */
    public function rowExistsWithTime(int $rowId, string $timeString) : bool;

    /**
     * Gets the version of the row with the given $rowId at the given time.
     * If the row does not exist at the given time, it returns null.
     *
     * @return array<string, mixed>|null
     * @throws InvalidTimeStringException
     */
    public function getRowWithTime(int $rowId, string $timeString) : ?array;

    /**
     * Returns an iterator with versions of rows that match the key/value pairs in the given $theRow
     * at the given time
     *
     * @param array<string, mixed> $rowToMatch
     * @throws InvalidRow
     * @throws InvalidTimeStringException
     */
    public function findRowsWithTime(array $rowToMatch, int $maxResults, string $timeString) : ResultsIterator;

    /**
     * Searches the datatable for rows that match the given $searchSpec array and $searchType
     * at the given time
     *
     * @param array<SearchSpec> $searchSpecArray
     * @throws InvalidSearchSpec
     * @throws InvalidSearchType
     * @throws InvalidRow
     * @throws InvalidTimeStringException
     */
    public function searchWithTime(array $searchSpecArray, SearchType $searchType, string $timeString, int $maxResults = 0): ResultsIterator;

    /**
     * Creates a new version of the given row that is valid from the given time.
     *
     * Assumes that the given time is later than the last version of the row.
     *
     * @throws InvalidTimeStringException
     * @throws RowDoesNotExist
     * @throws InvalidRow
     * @throws InvalidRowUpdateTime
     */
    public function updateRowWithTime(array $theRow, string $timeString) : void;

    /**
     * Makes a row non-existent after the given time.
     *
     * It does not delete any previous version of the row. It simply makes the last version of the row
     * be invalid after the given time.
     *
     * @throws InvalidTimeStringException
     * @throws InvalidRowUpdateTime
     */
    public function deleteRowWithTime(int $rowId, string $timeString) : int;

    /**
     * Returns an array with all the different versions of the row with the given $rowId
     *
     * @throws RowDoesNotExist
     */
    public function getRowHistory(int $rowId) : array;

}
