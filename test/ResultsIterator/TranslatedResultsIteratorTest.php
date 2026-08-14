<?php

namespace ThomasInstitut\DataTable\ResultsIterator;

use PHPUnit\Framework\Attributes\CoversClass;
use ThomasInstitut\DataTable\DataTableWithSchema;
use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\InMemoryDataTableWithSchema;
use ThomasInstitut\DataTable\ReferenceTests\ResultsIteratorReferenceTestCase;
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\ColumnDefinition;
use ThomasInstitut\DataTable\Schema\DataTableSchema;

#[CoversClass(ArrayResultsIterator::class)]
class TranslatedResultsIteratorTest extends ResultsIteratorReferenceTestCase
{
    /**
     * @throws InvalidColumnDefinitionsArray
     */
    public function createDataTable() : DataTableWithSchema {
        return new InMemoryDataTableWithSchema(new DataTableSchema([ new ColumnDefinition('id', ColumnDataType::Id), new ColumnDefinition('value', ColumnDataType::Integer)]));
    }

}