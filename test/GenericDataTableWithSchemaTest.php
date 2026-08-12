<?php

namespace ThomasInstitut\DataTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\ColumnDefinition;

#[CoversClass(GenericDataTableWithSchema::class)]
class GenericDataTableWithSchemaTest extends TestCase
{

    /**
     * @throws InvalidColumnDefinitionsArray
     * @throws RowAlreadyExists
     */
    #[Test]
    public function testBasic(): void
    {
        $columDefs = [
            (new ColumnDefinition('id', ColumnDataType::Id))->withDbColumn('idx'),
            (new ColumnDefinition('name', ColumnDataType::Text))->withDbColumn('nombre'),
            (new ColumnDefinition('age', ColumnDataType::Integer))->withDbColumn('edad'),
        ];
        $table = new GenericDataTableWithSchema(new InMemoryDataTable(), $columDefs);

        $table->createRow(['name' => 'John', 'age' => 30]);

        $allRows = $table->getAllRows();
        $this->assertEquals(1, $allRows->count());

        $firstRow = $allRows->getFirst();
        $this->assertEquals('John', $firstRow['name']);
        $this->assertEquals(30, $firstRow['age']);

    }
}