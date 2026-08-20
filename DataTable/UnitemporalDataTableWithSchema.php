<?php

declare(strict_types=1);

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
use ThomasInstitut\TimeString\TimeString;

/**
 *
 * A DataTable in which rows are versioned and conform to a data schema.
 *
 * Just as with a regular DataTable, the table can be understood as being composed of rows, each one with
 * a unique ID. Each row is versioned with `valid_from` and `valid_until` columns (implementations MUST allow
 * custom names for these columns). All CRUD operations are marked with a time string, and all previous data is
 * preserved, so a complete history of the row is available. Normal DataTable operations are simply
 * tagged as occurring at the present time.
 *
 * All input rows MUST conform to a given schema, and it is possible to have different column names for
 * input/output rows and rows stored in the underlying database.
 *
 * Time is expressed as a string in the format `YYYY-MM-DD HH:MM:SS.xxxxxx` without any timezone information.
 * No timezone information is stored in the database.
 *
 * It is up to the user to ensure that the time string is in the correct format and to deal with timezones.
 *
 * @see UnitemporalDataTable
 * @see TimeString
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
     *
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
     * @throws InvalidTimeStringException
     */
    public function searchWithTime(array $searchSpecArray, SearchType $searchType, string $timeString, int $maxResults = 0): ResultsIterator;

    /**
     * Creates a new version of the given row that is valid from the given time.
     *
     * The given time MUST be later than the last version of the row. Otherwise, and InvalidRowUpdateTimeException is thrown.
     *
     * @param array<string, mixed> $theRow
     * @throws InvalidTimeStringException
     * @throws RowDoesNotExist
     * @throws InvalidRow
     * @throws InvalidRowUpdateTime
     */
    public function updateRowWithTime(array $theRow, string $timeString) : void;

    /**
     * Makes a row non-existent after the given time.
     *
     * The given time MUST be later than the last version of the row. Otherwise, and InvalidRowUpdateTimeException is thrown.
     *
     * @throws InvalidTimeStringException
     * @throws InvalidRowUpdateTime
     */
    public function deleteRowWithTime(int $rowId, string $timeString) : int;

    /**
     * Returns an array with all the different versions of the row with the given $rowId
     * ordered by ascending time.
     *
     * @throws RowDoesNotExist
     * @return array<array<string, mixed>>
     */
    public function getRowHistory(int $rowId) : array;

}
