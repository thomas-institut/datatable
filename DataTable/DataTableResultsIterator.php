<?php
/*
 * The MIT License
 *
 * Copyright 2017-24 Thomas-Institut, Universität zu Köln.
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

use Countable;
use Iterator;

/**
 * An iterator over a set of results from a DataTable search
 *
 * Apart from the normal iterator functions that allows iterating over the results
 * with a foreach loop, DataTableResultsIterator provides two convenience
 * methods:
 *
 * * count(): returns the number of results without traversing the result set
 *   (implements the Countable interface, so count($iterator) works)
 * * getFirst(): returns the first result or null if there are no results
 *
 * The iterator strictly returns only arrays as results and iterator keys
 * are strictly consecutive integers.
 *
 *
 * Depending on the DataTable implementation that generates the result set, it might
 * not be possible to rewind the iterator. In particular, results from a PDO source,
 * for example, MySql, cannot be rewound.
 *
 */
interface DataTableResultsIterator extends Iterator, Countable
{

    /**
     * Returns the number of results without traversing the result set.
     *
     * It is better to use this function instead of PHP's _iterator_count_
     *
     * @return int
     */
    public function count() : int;

    /**
     * Returns the key/index of the current result.
     *
     * It is always an integer. Results are numbered from 0 to ($this->count() - 1).
     *
     * @return int
     */
    public function key(): int;


    /**
     * Returns the current result, which is always an array or null if the iterator has gone over its last
     * result.
     *
     * @return ?array
     */
    public function current(): ?array;


    /**
     * Returns the first result in the result set or null if there are no results.
     *
     * Normally this method will try to rewind the result set, which might not be possible
     * depending on the DataTable implementation that generated the results. This means that
     * in many cases it will not be possible to use a foreach loop on the iterator once this
     * method is called, or, conversely, it will not be possible to execute this method after
     * a foreach loop.
     *
     * @return ?array
     */
    public function getFirst() : ?array;

}