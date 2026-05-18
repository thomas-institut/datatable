<?php

namespace ThomasInstitut\DataTable\ResultsIterator;

use PHPUnit\Framework\Attributes\CoversClass;
use ThomasInstitut\DataTable\DataTable;
use ThomasInstitut\DataTable\InMemoryDataTable;
use ThomasInstitut\DataTable\ReferenceTests\ResultsIteratorReferenceTestCase;

#[CoversClass(ArrayResultsIterator::class)]
class ArrayResultsIteratorTest extends ResultsIteratorReferenceTestCase
{
    public function createDataTable() : DataTable {
        return new InMemoryDataTable();
    }

}