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


namespace ThomasInstitut\DataTable\Test;

include '../DataTable/DataTable.php';


use RunTimeException;
use ThomasInstitut\DataTable\ArrayDataTableResultsIterator;
use ThomasInstitut\DataTable\DataTableResultsIterator;
use ThomasInstitut\DataTable\GenericDataTable;

/**
 * Mockup class that fails to return new Ids for rows
 *
 * @author Rafael Nájera <rafael.najera@uni-koeln.de>
 */
class FailGetOneUnusedIdGenericDataTable extends GenericDataTable {


    const ERROR_CANNOT_GET_MAX_ID = 2001;
    


    public function findRows(array $rowToMatch, int $maxResults = 0): DataTableResultsIterator
    {
        return new ArrayDataTableResultsIterator([]);
    }

    public function deleteRow(int $rowId): int
    {
       return false;
    }

    public function rowExists(int $rowId): bool
    {
       return false;
    }

    public function getAllRows(): DataTableResultsIterator
    {
        return new ArrayDataTableResultsIterator([]);
    }

    public function getRow(int $rowId): array
    {
        return [];
    }

    public function getIdForKeyValue(string $key, mixed $value): int
    {
        return 0;
    }



    public function getMaxId(): int
    {
        $this->setError('Cannot get a max Id',self::ERROR_CANNOT_GET_MAX_ID );
        throw new RunTimeException($this->getErrorMessage(), $this->getErrorCode());
    }

    protected function realCreateRow(array $theRow): int
    {
        return 0;
    }

    protected function realUpdateRow(array $theRow): void
    {

    }


    public function search(array $searchSpecArray, int $searchType = self::SEARCH_AND, int $maxResults = 0): DataTableResultsIterator
    {
        return new ArrayDataTableResultsIterator([]);
    }


    public function getMaxValueInColumn(string $columnName): int
    {
        return 0;
    }


}
