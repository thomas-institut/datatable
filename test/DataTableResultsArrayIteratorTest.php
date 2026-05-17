<?php

namespace ThomasInstitut\DataTable;

use PHPUnit\Framework\Attributes\CoversClass;
use ThomasInstitut\DataTable\ReferenceTests\DataTableResultsIteratorReferenceTestCase;
use ThomasInstitut\DataTable\ResultsIterator\ArrayResultsIterator;

#[CoversClass(ArrayResultsIterator::class)]
class DataTableResultsArrayIteratorTest extends DataTableResultsIteratorReferenceTestCase
{
    public function createDataTable() : DataTable {
        return new InMemoryDataTable();
    }

}