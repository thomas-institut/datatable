<?php

/*
 * The MIT License
 *
 * Copyright 2018 Rafael Nájera <rafael@najera.ca>.
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


namespace DataTable\Test;

include '../DataTable/DataTable.php';

use DataTable\DataTable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Mockup class that fails to return new Ids for rows
 *
 * @author Rafael Nájera <rafael.najera@uni-koeln.de>
 */
class FailGetOneUnusedIdDataTable extends DataTable {


    const ERROR_CANNOT_GET_MAX_ID = 2001;
    


    /**
     * Searches the table for rows with the same data as the given row
     *
     * Only the keys given in $theRow are checked; so, for example,
     * if $theRow is missing a key that exists in the actual rows
     * in the table, those missing keys are ignored and the method
     * will return any row that matches exactly the given keys independently
     * of the missing ones.
     *
     * if $maxResults > 0, an array of max $maxResults will be returned
     * if $maxResults <= 0, all results will be returned
     *
     * @param array $theRow
     * @param int $numResults
     * @return array the results
     */
    public function findRows(array $theRow, int $numResults = 0): array
    {
        return [];
    }

    /**
     * Deletes the row with the given Id.
     * If there's no row with the given Id it must return false
     *
     * @param int $rowId
     * @return bool
     */
    public function deleteRow(int $rowId): bool
    {
       return false;
    }

    /**
     * @param int $rowId
     * @return bool true if the row with the given Id exists
     */
    public function rowExists(int $rowId): bool
    {
       return false;
    }

    /**
     * Gets all rows in the table
     *
     * @return array
     */
    public function getAllRows(): array
    {
        return [];
    }

    /**
     * Gets the row with the given row Id.
     * If the row does not exist throws an InvalidArgument exception
     *
     * @param int $rowId
     * @return array The row
     * @throws InvalidArgumentException
     */
    public function getRow(int $rowId): array
    {
        return [];
    }

    /**
     * Returns the id of one row in which $row[$key] === $value
     * or false if such a row cannot be found or an error occurred whilst
     * trying to find it.
     *
     * @param string $key
     * @param mixed $value
     * @return int
     */
    public function getIdForKeyValue(string $key, $value): int
    {
        return 0;
    }

    /**
     * @return int the max id in the table
     */
    protected function getMaxId(): int
    {
        $this->setErrorCode(self::ERROR_CANNOT_GET_MAX_ID);
        $this->setErrorMessage('Cannot get a max Id');
        throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
    }

    /**
     * Creates a row in the table, returns the id of the newly created
     * row.
     *
     * @param array $theRow
     * @return int
     */
    protected function realCreateRow(array $theRow): int
    {
        return 0;
    }

    /**
     * Updates the given row, which must have a valid Id.
     * If there's not row with that id, it throw an InvalidArgument exception.
     *
     * Must throw a Runtime Exception if the row was not updated
     *
     * @param array $theRow
     * @return bool
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    protected function realUpdateRow(array $theRow): void
    {

    }
}
