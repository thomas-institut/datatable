<?php

namespace ThomasInstitut\DataTable;

use Iterator;

interface DataTableResultsIterator extends Iterator
{

    /**
     * Returns the number of results.
     *
     * In general, it is better to use this function instead of _iterator_count_
     * @return int
     */
    public function count() : int;

    public function getFirst() : array;

}