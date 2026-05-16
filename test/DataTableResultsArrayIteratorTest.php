<?php

namespace ThomasInstitut\DataTable;

require_once 'DataTableResultsIteratorReferenceTestCase.php';

class DataTableResultsArrayIteratorTest extends DataTableResultsIteratorReferenceTestCase
{
    public function createDataTable() : DataTable {
        return new InMemoryDataTable();
    }

}