<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\ColumnDefinition;
use ThomasInstitut\DataTable\Schema\DataTableSchema;
use ThomasInstitut\DataTable\Schema\NoOpRowValueTranslator;

#[CoversClass(GenericDataTableWithSchema::class)]
final class GenericDataTableWithSchemaTest extends TestCase
{

    /**
     * @throws InvalidColumnDefinitionsArray
     */
    public function testConstructorAddsRequiredDataTypes(): void
    {

        $table = new GenericDataTableWithSchema(
            new InMemoryDataTable(),
            new DataTableSchema([ new ColumnDefinition('id', ColumnDataType::Id)]),
            new NoOpRowValueTranslator(),
            null,
            []
        );

        $supportedDataTypes = $table->getSupportedDataTypes();
        foreach(DataTableWithSchema::MandatorySupportedDataTypes as $dataType)
        {
            $this->assertContains($dataType, $supportedDataTypes);
        }
    }

    public function testConstructorThrowsOnInvalidColumnDefinitions(): void
    {
        $this->expectException(InvalidColumnDefinitionsArray::class);
        new GenericDataTableWithSchema(
            new InMemoryDataTable(),
            new DataTableSchema([ new ColumnDefinition('id', ColumnDataType::TimeString)]),
            new NoOpRowValueTranslator(),
            null,
            []
        );
    }
}
