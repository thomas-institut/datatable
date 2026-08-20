<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable;

use PHPUnit\Framework\Attributes\CoversClass;
use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\ReferenceTests\DataTableWithSchemaReferenceTestCase;
use ThomasInstitut\DataTable\Schema\ColumnDefinition;
use ThomasInstitut\DataTable\Schema\DataTableSchema;

#[CoversClass(InMemoryDataTableWithSchema::class)]
final class InMemoryDataTableWithSchemaTest extends DataTableWithSchemaReferenceTestCase
{
    /**
     * @param array<ColumnDefinition> $columnDefinitions
     * @throws InvalidColumnDefinitionsArray
     */
    public function getTestTable(array $columnDefinitions): DataTableWithSchema
    {
        return new InMemoryDataTableWithSchema(new DataTableSchema($columnDefinitions));
    }
}
