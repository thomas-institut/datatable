<?php

namespace ThomasInstitut\DataTable;

use Iterator;

/**
 * An iterator over a set of results from a DataTable search
 *
 * Apart from the normal iterator functions that allows iterating over the results
 * with a foreach loop, DataTableResultsIterator provides two convenience
 * methods:
 *
 * * count(): returns the number of results without traversing the result set
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
interface DataTableResultsIterator extends Iterator
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