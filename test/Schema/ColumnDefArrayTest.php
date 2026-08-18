<?php

namespace ThomasInstitut\DataTable\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColumnDefArray::class)]
class ColumnDefArrayTest extends TestCase
{

    public function testReturnsNullOnNotFound(): void
    {
        $columnDefinitions = [
            (new ColumnDefinition('name', ColumnDataType::VarChar))
                ->withDbColumn('full_name')
                ->withTypeLength(100)
        ];

        $this->assertNull(ColumnDefArray::getIdDbColumn($columnDefinitions));
        $this->assertNull(ColumnDefArray::getIdKey($columnDefinitions));
        $this->assertNull(ColumnDefArray::getColumnDef($columnDefinitions, 'missing'));
    }

    public function testValidColumnDefinitionsHaveNoErrors(): void
    {
        $columnDefinitions = [
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition('name', ColumnDataType::VarChar))
                ->withDbColumn('full_name')
                ->withTypeLength(100),
        ];

        $this->assertSame([], ColumnDefArray::validate($columnDefinitions, ColumnDataType::cases()));
    }

    public function testMissingIdColumnIsReported(): void
    {
        $columnDefinitions = [
            (new ColumnDefinition('name', ColumnDataType::Text))->withRequired(true),
        ];

        $this->assertSame(
            ['No id column found in column definitions'],
            ColumnDefArray::validate($columnDefinitions, ColumnDataType::cases()),
        );
    }

    public function testInvalidElementIsReportedAndDoesNotStopValidation(): void
    {
        $columnDefinitions = [
            'not a column definition',
            (new ColumnDefinition('name', ColumnDataType::Text))->withRequired(true),
        ];

        $this->assertSame(
            [
                'Element at index 0 is not a ColumnDef object.',
                'No id column found in column definitions',
            ],
            ColumnDefArray::validate($columnDefinitions, ColumnDataType::cases()),
        );
    }

    public function testNonIntegerArrayKeyIsReported(): void
    {
        $columnDefinitions = [
            new ColumnDefinition('id', ColumnDataType::Id),
            'name' => (new ColumnDefinition('name', ColumnDataType::Text))->withRequired(true),
        ];

        $this->assertSame(
            ["Column definition array key must be an integer, but found 'name' of type string"],
            ColumnDefArray::validate($columnDefinitions, ColumnDataType::cases()),
        );
    }

    public function testUnsupportedDataTypeIsReported(): void
    {
        $columnDefinitions = [
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition('name', ColumnDataType::Text))->withRequired(true),
        ];

        $this->assertSame(
            ["Column at index 1 has unsupported data type: 'text'"],
            ColumnDefArray::validate($columnDefinitions, [ColumnDataType::Id]),
        );
    }

    public function testTypesWithoutDefaultsMustBeRequired(): void
    {
        $columnDefinitions = [
            new ColumnDefinition('id', ColumnDataType::Id),
            new ColumnDefinition('name', ColumnDataType::Text),
        ];

        $this->assertSame(
            ['Column at index 1 must have required = true since it is of type text.'],
            ColumnDefArray::validate($columnDefinitions, ColumnDataType::cases()),
        );
    }

    #[DataProvider('invalidKeyProvider')]
    public function testInvalidRowKeyIsReported(string $rowKey): void
    {
        $columnDefinitions = [
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition($rowKey, ColumnDataType::Text))->withRequired(true),
        ];

        $this->assertContains(
            "Column at index 1 has invalid rowKey: '$rowKey'",
            ColumnDefArray::validate($columnDefinitions, ColumnDataType::cases()),
        );
    }

    public static function invalidKeyProvider(): array
    {
        return [
            'empty' => [''],
            'leading space' => [' name'],
            'trailing space' => ['name '],
            'internal space' => ['first name'],
        ];
    }

    public function testInvalidDatabaseColumnIsReported(): void
    {
        $columnDefinitions = [
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition('name', ColumnDataType::Text))
                ->withDbColumn('full name')
                ->withRequired(true),
        ];

        $this->assertSame(
            ["Column at index 1 has invalid dbColumn: 'full name'"],
            ColumnDefArray::validate($columnDefinitions, ColumnDataType::cases()),
        );
    }

    public function testDuplicateRowKeyIsReported(): void
    {
        $columnDefinitions = [
            (new ColumnDefinition('name', ColumnDataType::Text))->withRequired(true),
            (new ColumnDefinition('name', ColumnDataType::Text))->withRequired(true),
            new ColumnDefinition('id', ColumnDataType::Id),
        ];

        $this->assertContains(
            "Duplicate rowKey: 'name'.",
            ColumnDefArray::validate($columnDefinitions, ColumnDataType::cases()),
        );
    }

    public function testDuplicateEffectiveDatabaseColumnIsReported(): void
    {
        $columnDefinitions = [
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition('first', ColumnDataType::Text))
                ->withDbColumn('shared_name')
                ->withRequired(true),
            (new ColumnDefinition('second', ColumnDataType::Text))
                ->withDbColumn('shared_name')
                ->withRequired(true),
        ];

        $this->assertContains(
            "Duplicate database column name: 'shared_name'.",
            ColumnDefArray::validate($columnDefinitions, ColumnDataType::cases()),
        );
    }

    public function testRowKeyIsUsedAsEffectiveDatabaseColumnWhenDbColumnIsNull(): void
    {
        $columnDefinitions = [
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition('first', ColumnDataType::Text))->withRequired(true),
            (new ColumnDefinition('second', ColumnDataType::Text))
                ->withDbColumn('first')
                ->withRequired(true),
        ];

        $this->assertContains(
            "Duplicate database column name: 'first'.",
            ColumnDefArray::validate($columnDefinitions, ColumnDataType::cases()),
        );
    }

    #[DataProvider('invalidVarcharLengthProvider')]
    public function testInvalidVarcharLengthIsReported(int $typeLength): void
    {
        $columnDefinitions = [
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition('name', ColumnDataType::VarChar))
                ->withTypeLength($typeLength),
        ];

        $this->assertContains(
            "Column 'name' is Varchar but has invalid typeLength: $typeLength.",
            ColumnDefArray::validate($columnDefinitions, ColumnDataType::cases()),
        );
    }

    public static function invalidVarcharLengthProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    public function testPositiveVarcharLengthIsValid(): void
    {
        $columnDefinitions = [
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition('name', ColumnDataType::VarChar))
                ->withTypeLength(1),
        ];

        $this->assertSame([], ColumnDefArray::validate($columnDefinitions, ColumnDataType::cases()));
    }
}