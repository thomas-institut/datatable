<?php

namespace ThomasInstitut\DataTable\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColumnDefArrayValidator::class)]
class ColumnDefArrayValidatorTest extends TestCase
{
    public function testValidColumnDefinitionsHaveNoErrors(): void
    {
        $columnDefinitions = [
            'id' => new ColumnDefinition('id', ColumnDataType::Id),
            'name' => (new ColumnDefinition('name', ColumnDataType::VarChar))
                ->withDbColumn('full_name')
                ->withTypeLength(100),
        ];

        $this->assertSame([], ColumnDefArrayValidator::validate($columnDefinitions, ColumnDataType::cases()));
    }

    public function testMissingIdColumnIsReported(): void
    {
        $columnDefinitions = [
            'name' => new ColumnDefinition('name', ColumnDataType::Text),
        ];

        $this->assertSame(
            ['No id column found in column definitions'],
            ColumnDefArrayValidator::validate($columnDefinitions, ColumnDataType::cases()),
        );
    }

    public function testInvalidElementIsReportedAndDoesNotStopValidation(): void
    {
        $columnDefinitions = [
            'invalid' => 'not a column definition',
            'name' => new ColumnDefinition('name', ColumnDataType::Text),
        ];

        $this->assertSame(
            [
                'Element at key invalid is not a ColumnDef object.',
                'No id column found in column definitions',
            ],
            ColumnDefArrayValidator::validate($columnDefinitions, ColumnDataType::cases()),
        );
    }

    #[DataProvider('invalidKeyProvider')]
    public function testInvalidRowKeyIsReported(string $rowKey): void
    {
        $columnDefinitions = [
            'id' => new ColumnDefinition('id', ColumnDataType::Id),
            'column' => new ColumnDefinition($rowKey, ColumnDataType::Text),
        ];

        $this->assertContains(
            "Column at index column has invalid rowKey: '$rowKey'",
            ColumnDefArrayValidator::validate($columnDefinitions, ColumnDataType::cases()),
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
            'id' => new ColumnDefinition('id', ColumnDataType::Id),
            'name' => (new ColumnDefinition('name', ColumnDataType::Text))
                ->withDbColumn('full name'),
        ];

        $this->assertSame(
            ["Column at index name has invalid dbColumn: 'full name'"],
            ColumnDefArrayValidator::validate($columnDefinitions, ColumnDataType::cases()),
        );
    }

    public function testDuplicateRowKeyIsReported(): void
    {
        $columnDefinitions = [
            'first' => new ColumnDefinition('name', ColumnDataType::Text),
            'second' => new ColumnDefinition('name', ColumnDataType::Text),
            'id' => new ColumnDefinition('id', ColumnDataType::Id),
        ];

        $this->assertContains(
            "Duplicate rowKey: 'name'.",
            ColumnDefArrayValidator::validate($columnDefinitions, ColumnDataType::cases()),
        );
    }

    public function testDuplicateEffectiveDatabaseColumnIsReported(): void
    {
        $columnDefinitions = [
            'id' => new ColumnDefinition('id', ColumnDataType::Id),
            'first' => (new ColumnDefinition('first', ColumnDataType::Text))
                ->withDbColumn('shared_name'),
            'second' => (new ColumnDefinition('second', ColumnDataType::Text))
                ->withDbColumn('shared_name'),
        ];

        $this->assertContains(
            "Duplicate database column name: 'shared_name'.",
            ColumnDefArrayValidator::validate($columnDefinitions, ColumnDataType::cases()),
        );
    }

    public function testRowKeyIsUsedAsEffectiveDatabaseColumnWhenDbColumnIsNull(): void
    {
        $columnDefinitions = [
            'id' => new ColumnDefinition('id', ColumnDataType::Id),
            'first' => new ColumnDefinition('first', ColumnDataType::Text),
            'second' => (new ColumnDefinition('second', ColumnDataType::Text))
                ->withDbColumn('first'),
        ];

        $this->assertContains(
            "Duplicate database column name: 'first'.",
            ColumnDefArrayValidator::validate($columnDefinitions, ColumnDataType::cases()),
        );
    }

    #[DataProvider('invalidVarcharLengthProvider')]
    public function testInvalidVarcharLengthIsReported(int $typeLength): void
    {
        $columnDefinitions = [
            'id' => new ColumnDefinition('id', ColumnDataType::Id),
            'name' => (new ColumnDefinition('name', ColumnDataType::VarChar))
                ->withTypeLength($typeLength),
        ];

        $this->assertContains(
            "Column 'name' is Varchar but has invalid typeLength: $typeLength.",
            ColumnDefArrayValidator::validate($columnDefinitions, ColumnDataType::cases()),
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
            'id' => new ColumnDefinition('id', ColumnDataType::Id),
            'name' => (new ColumnDefinition('name', ColumnDataType::VarChar))
                ->withTypeLength(1),
        ];

        $this->assertSame([], ColumnDefArrayValidator::validate($columnDefinitions, ColumnDataType::cases()));
    }
}