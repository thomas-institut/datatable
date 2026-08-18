<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable\ResultsIterator;

use PHPUnit\Framework\Attributes\CoversClass;
use ThomasInstitut\DataTable\DataTable;
use ThomasInstitut\DataTable\DataTableWithSchema;
use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\InMemoryDataTableWithSchema;
use ThomasInstitut\DataTable\ReferenceTests\ResultsIteratorReferenceTestCase;
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\ColumnDefinition;
use ThomasInstitut\DataTable\Schema\DataTableSchema;

#[CoversClass(TranslatedResultsIterator::class)]
final class TranslatedResultsIteratorTest extends ResultsIteratorReferenceTestCase
{
    /**
     * @throws InvalidColumnDefinitionsArray
     */
    public function createDataTable() : DataTableWithSchema {
        return new InMemoryDataTableWithSchema(new DataTableSchema([
            new ColumnDefinition(DataTable::DEFAULT_ID_COLUMN_NAME, ColumnDataType::Id),
            new ColumnDefinition(ResultsIteratorReferenceTestCase::INT_COLUM, ColumnDataType::Integer)
        ]));
    }

}
