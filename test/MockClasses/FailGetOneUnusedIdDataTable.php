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
    
    
    public function rowExistsById(int $rowId) {
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
}
