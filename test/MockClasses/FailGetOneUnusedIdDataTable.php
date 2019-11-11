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

/**
 * Mockup class that fails to return new Ids for rows
 *
 * @author Rafael Nájera <rafael.najera@uni-koeln.de>
 */
class FailGetOneUnusedIdDataTable extends DataTable {
    
    public function getOneUnusedId() {
        return false;
    }
    
    
    public function rowExists(int $rowId) {
        return false;
    }
    
    public function realFindRows($theRow, $maxResults) {
        return false;
    }
    
    public function getAllRows() {
        return false;
    }
    
    public function getRow($rowId) {
        return false;
    }
    
    
    public function getMaxId() {
        return 1;
    }
    
    public function getIdForKeyValue($key, $value) {
        return false;
    }
    
    public function realCreateRow($theRow) {
        return false;
    }
    
    public function realDeleteRow($rowId) {
        return false;
    }
    
    public function realUpdateRow($theRow) {
        return false;
    }

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
        // TODO: Implement findRows() method.
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
        // TODO: Implement deleteRow() method.
    }
}
