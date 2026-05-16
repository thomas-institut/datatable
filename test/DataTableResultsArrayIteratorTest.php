<?php

namespace ThomasInstitut\DataTable;

use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DataTableResultsArrayIterator::class)]
class DataTableResultsArrayIteratorTest extends DataTableResultsIteratorReferenceTestCase
{
    public function createDataTable() : DataTable {
        return new InMemoryDataTable();
    }

}