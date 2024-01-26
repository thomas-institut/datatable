<?php

namespace ThomasInstitut\DataTable;

require '../vendor/autoload.php';
require_once 'config.php';
require_once 'DataTableResultsIteratorReferenceTestCase.php';

class DataTestCaseTableResultsArrayIteratorTest extends DataTableResultsIteratorReferenceTestCase
{
    public function createDataTable() : DataTable {
        return new InMemoryDataTable();
    }

}