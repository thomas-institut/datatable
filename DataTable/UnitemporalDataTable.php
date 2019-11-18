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


namespace DataTable;

/**
 * Defines a class that provides the same methods as a DataTable but with a
 * time indication
 *
 * This is a transitional interface. In a later version of DataTable it will become
 * an abstract class to implement Unitemporal DataTables with any DataTable foundation
 *
 * @package DataTable
 */
interface UnitemporalDataTable
{

    // Other constants
    const END_OF_TIMES = '9999-12-31 23:59:59.999999';


    public function createRowWithTime(array $theRow, string $timeString) : int;

    public function rowExistsWithTime(int $rowId, string $timeString) : bool;
    public function getRowWithTime(int $rowId, string $timeString) : array;
    public function findRowsWithTime($theRow, $maxResults, string $timeString) : array;
    public function searchWithTime(array $searchSpec, int $searchType, string $timeString, int $maxResults = 0): array;

    public function updateRowWithTime(array $theRow, string $timeString) : void;

    public function deleteRowWithTime(int $rowId, string $timeString) : int;






}