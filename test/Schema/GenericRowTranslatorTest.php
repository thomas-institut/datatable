<?php

namespace ThomasInstitut\DataTable\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\Exception\InvalidRow;

#[CoversClass(GenericRowTranslator::class)]
class GenericRowTranslatorTest extends TestCase
{
    /**
     * @throws InvalidColumnDefinitionsArray|InvalidRow
     */
    #[DataProvider('roundTripProvider')]
    public function testRowsRoundTripThroughDatabaseTranslation(
        RowValueTranslator $rowValueTranslator,
        array              $columnDefinitions,
        array              $row,
        array              $expectedDatabaseRow,
    ): void
    {
        $translator = new GenericRowTranslator($rowValueTranslator, $columnDefinitions);

        $databaseRow = $translator->inputRowToDb($row);

        $this->assertSame($expectedDatabaseRow, $databaseRow);
        $this->assertSame($row, $translator->dbRowToOutputRow($databaseRow));
    }

    public static function roundTripProvider(): array
    {
        return [
            'no-op values with database aliases' => [
                new NoOpRowValueTranslator(),
                [
                    (new ColumnDefinition('id', ColumnDataType::Id))->withDbColumn('row_id'),
                    (new ColumnDefinition('name', ColumnDataType::VarChar))
                        ->withDbColumn('full_name')
                        ->withTypeLength(100),
                    (new ColumnDefinition('description', ColumnDataType::Text))->withRequired(true),
                    (new ColumnDefinition('age', ColumnDataType::Integer))->withDbColumn('years'),
                    new ColumnDefinition('enabled', ColumnDataType::Boolean),
                    (new ColumnDefinition('metadata', ColumnDataType::Serializable))
                        ->withDbColumn('extra_data')
                        ->withRequired(true),
                ],
                [
                    'id' => 42,
                    'name' => 'Ada',
                    'description' => 'First programmer',
                    'age' => -12,
                    'enabled' => true,
                    'metadata' => ['roles' => ['admin', 'editor']],
                ],
                [
                    'row_id' => 42,
                    'full_name' => 'Ada',
                    'description' => 'First programmer',
                    'years' => -12,
                    'enabled' => true,
                    'extra_data' => ['roles' => ['admin', 'editor']],
                ],
            ],
            'string database values with database aliases' => [
                new StringValuesDbRowValueTranslator(),
                [
                    (new ColumnDefinition('identifier', ColumnDataType::Id))->withDbColumn('id_value'),
                    (new ColumnDefinition('title', ColumnDataType::VarChar))->withTypeLength(100),
                    (new ColumnDefinition('body', ColumnDataType::Text))
                        ->withDbColumn('text_value')
                        ->withRequired(true),
                    new ColumnDefinition('count', ColumnDataType::Integer),
                    (new ColumnDefinition('visible', ColumnDataType::Boolean))->withDbColumn('is_visible'),
                    (new ColumnDefinition('attributes', ColumnDataType::Serializable))->withRequired(true),
                ],
                [
                    'identifier' => 7,
                    'title' => 'A title',
                    'body' => 'A longer text value',
                    'count' => 0,
                    'visible' => false,
                    'attributes' => ['priority' => 3, 'tags' => ['php', 'testing']],
                ],
                [
                    'id_value' => '7',
                    'title' => 'A title',
                    'text_value' => 'A longer text value',
                    'count' => '0',
                    'is_visible' => '0',
                    'attributes' => serialize(['priority' => 3, 'tags' => ['php', 'testing']]),
                ],
            ],
        ];
    }

    /**
     * @throws InvalidColumnDefinitionsArray
     */
    public function testInputRowWithUndefinedColumnThrowsException(): void
    {
        $translator = new GenericRowTranslator(
            new NoOpRowValueTranslator(),
            [new ColumnDefinition('id', ColumnDataType::Id)],
        );

        $this->expectException(InvalidRow::class);
        $this->expectExceptionMessage("Column 'unknown' is not defined in the schema");

        $translator->inputRowToDb(['unknown' => 'value']);
    }

    /**
     * @throws InvalidColumnDefinitionsArray
     */
    public function testDatabaseRowWithUndefinedColumnThrowsException(): void
    {
        $translator = new GenericRowTranslator(
            new NoOpRowValueTranslator(),
            [new ColumnDefinition('id', ColumnDataType::Id)],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Column 'unknown' is not defined in the DataTable");

        $translator->dbRowToOutputRow(['unknown' => 'value']);
    }
}