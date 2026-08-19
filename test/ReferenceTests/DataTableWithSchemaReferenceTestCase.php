<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable\ReferenceTests;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\RandomException;
use ThomasInstitut\DataTable\DataTableWithSchema;
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\DataTable\Exception\InvalidRow;
use ThomasInstitut\DataTable\Exception\InvalidSearchSpec;
use ThomasInstitut\DataTable\Exception\InvalidSearchType;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\ColumnDefinition;
use ThomasInstitut\DataTable\SearchCondition;
use ThomasInstitut\DataTable\SearchSpec;
use ThomasInstitut\TimeString\TimeString;

/**
 * Reference tests for DataTableWithSchema implementations.
 *
 * Implementations provide a schema-backed table through getTestTable(). The
 * test cases use DataTableWithSchema::MandatorySupportedDataTypes and exercise
 * optional data types when the implementation supports them.
 */
abstract class DataTableWithSchemaReferenceTestCase extends TestCase
{
    /**
     * @param array<ColumnDefinition> $columnDefinitions
     */
    abstract public function getTestTable(array $columnDefinitions): DataTableWithSchema;

    /**
     * @throws RandomException
     */
    #[Test]
    public function testBasic(): void
    {
        $columnDefinitions = [
            (new ColumnDefinition('id', ColumnDataType::Id))->withDbColumn('idx'),
            (new ColumnDefinition('name', ColumnDataType::Text))->withDbColumn('nombre')->withRequired(true),
        ];
        $columnDefinitions = array_merge($columnDefinitions, $this->getOptionalColumnDefinitions());
        $columnDefinitions[] = (new ColumnDefinition('age', ColumnDataType::Integer))->withDbColumn('edad');
        $columnDefinitions[] = (new ColumnDefinition('active', ColumnDataType::Boolean))->withRequired(true);
        $table = $this->getTestTable($columnDefinitions);

        $numRows = 100;
        $rowsToTest = $this->makeFakeValidRows($columnDefinitions, $numRows);

        $rowIdMap = array_map($table->createRow(...), $rowsToTest);

        $createdIds = array_values($rowIdMap);
        sort($createdIds);
        $this->assertSame($createdIds, iterator_to_array($table->getUniqueIds()));

        $allRows = $table->getAllRows();
        $this->assertCount($numRows, $allRows);

        $this->assertNull($table->getRow(999999999));
        $this->assertFalse($table->rowExists(8888888));

        foreach ($rowIdMap as $index => $id) {
            $this->assertTrue($table->rowExists($id));
            $fetchedRow = $table->getRow($id);
            $originalRow = $rowsToTest[$index];
            foreach ($originalRow as $columnName => $value) {
                /** @phpstan-ignore offsetAccess.notFound */
                $this->assertEquals($value, $fetchedRow[$columnName]);
            }
        }
    }

    /**
     * @throws InvalidRow
     * @throws RowAlreadyExists
     */
    #[Test]
    public function testFindRows(): void
    {
        $table = $this->getTestTable([
            (new ColumnDefinition('id', ColumnDataType::Id))->withDbColumn('idx'),
            (new ColumnDefinition('name', ColumnDataType::Text))->withDbColumn('nombre')->withRequired(true),
            (new ColumnDefinition('age', ColumnDataType::Integer))->withDbColumn('edad'),
        ]);

        $johnId = $table->createRow(['name' => 'John', 'age' => 30]);
        $janeId = $table->createRow(['name' => 'Jane', 'age' => 30]);
        $table->createRow(['name' => 'John', 'age' => 45]);

        $rows = $table->findRows(['name' => 'John', 'age' => 30]);

        $this->assertCount(1, $rows);
        $this->assertArrayIsEqualToArrayOnlyConsideringListOfKeys(
            ['id' => $johnId, 'name' => 'John', 'age' => 30], $rows->getFirst() ?? $this->fail("Null first row"), ['id', 'name', 'age']);
        $this->assertCount(1, $table->findRows(['age' => 30], 1));
        $this->assertCount(0, $table->findRows(['name' => 'Missing']));
        /** @phpstan-ignore offsetAccess.notFound */
        $this->assertSame($janeId, $table->findRows(['name' => 'Jane'])->getFirst()['id']);

        $booleanTable = $this->getTestTable([
            new ColumnDefinition('id', ColumnDataType::Id),
            new ColumnDefinition('active', ColumnDataType::Boolean),
        ]);
        $activeId = $booleanTable->createRow(['active' => true]);
        $inactiveId = $booleanTable->createRow(['active' => false]);

        $this->assertCount(1, $booleanTable->findRows(['active' => true]));
        $this->assertArrayIsEqualToArrayOnlyConsideringListOfKeys(
            ['id' => $activeId, 'active' => true],
            $booleanTable->findRows(['active' => true])->getFirst() ?? $this->fail("Null first row"),
            ['id', 'active']
        );
        /** @phpstan-ignore offsetAccess.notFound */
        $this->assertSame($inactiveId, $booleanTable->findRows(['active' => false])->getFirst()['id']);

        $supportedDataTypes = $this->getTestTable([
            new ColumnDefinition('id', ColumnDataType::Id),
        ])->getSupportedDataTypes();
        if (in_array(ColumnDataType::VarChar, $supportedDataTypes, true)) {
            $varCharTable = $this->getTestTable([
                new ColumnDefinition('id', ColumnDataType::Id),
                (new ColumnDefinition('description', ColumnDataType::VarChar))->withTypeLength(64),
            ]);
            $descriptionId = $varCharTable->createRow(['description' => 'movie']);
            $varCharTable->createRow(['description' => 'book']);

            $rows = $varCharTable->findRows(['description' => 'movie']);

            $this->assertCount(1, $rows);
            $this->assertSame([
                'id' => $descriptionId,
                'description' => 'movie',
            ], $rows->getFirst());
            $this->assertCount(0, $varCharTable->findRows(['description' => 'missing']));
        }
    }

    /**
     * @param array<int, mixed> $values
     * @throws InvalidSearchType
     * @throws InvalidRow
     * @throws RowAlreadyExists
     * @throws InvalidSearchSpec
     */
    #[Test]
    #[DataProvider('searchProvider')]
    public function testSearch(
        ColumnDataType  $columnType,
        SearchCondition $condition,
        mixed           $searchValue,
        array           $values,
        int             $expectedCount,
    ): void
    {
        $supportedDataTypes = $this->getTestTable([
            new ColumnDefinition('id', ColumnDataType::Id),
        ])->getSupportedDataTypes();
        if (!in_array($columnType, $supportedDataTypes, true)) {
            $this->markTestSkipped("Test table does not support data type '$columnType->value'.");
        }

        $columnDefinition = new ColumnDefinition('value', $columnType);
        if ($columnType === ColumnDataType::VarChar) {
            $columnDefinition->withTypeLength(64);
        }
        if (in_array($columnType, ColumnDataType::NoDefaultTypes)) {
            $columnDefinition->withRequired(true);
        }
        $table = $this->getTestTable([
            new ColumnDefinition('id', ColumnDataType::Id),
            $columnDefinition,
        ]);

        $isSupported = false;
        foreach ($table->getSupportedSearchConditions() as $supportedSearchCondition) {
            if ($supportedSearchCondition->type === $columnType
                && in_array($condition, $supportedSearchCondition->conditions, true)
            ) {
                $isSupported = true;
                break;
            }
        }
        if (!$isSupported) {
            $this->markTestSkipped("Test table does not support search condition '$condition->value' for type '$columnType->value'.");
        }

        foreach ($values as $value) {
            $table->createRow(['value' => $value]);
        }

        $results = $table->search([
            new SearchSpec('value', $condition, $searchValue),
        ]);

        $this->assertCount($expectedCount, $results);
    }

    /**
     * @return array<string, array{ColumnDataType, SearchCondition, mixed, array<int, mixed>, int}>
     */
    public static function searchProvider(): array
    {
        $testCases = [];
        $orderedTypes = [
            ColumnDataType::Text,
            ColumnDataType::VarChar,
            ColumnDataType::Integer,
        ];

        foreach ($orderedTypes as $columnType) {
            $values = $columnType === ColumnDataType::Integer ? [-10, -1, 10] : ['art', 'movie', 'zeal'];
            foreach (SearchCondition::cases() as $condition) {
                $expectedCount = match ($condition) {
                    SearchCondition::Equals,
                    SearchCondition::LessThan,
                    SearchCondition::GreaterThan => 1,
                    SearchCondition::NotEquals,
                    SearchCondition::LessThanOrEquals,
                    SearchCondition::GreaterThanOrEquals => 2,
                };
                $testCases["$columnType->value-$condition->value"] = [$columnType, $condition, $values[1], $values, $expectedCount];
            }
        }

        foreach ([
                     SearchCondition::Equals,
                     SearchCondition::NotEquals,
                 ] as $condition) {
            $testCases["boolean-$condition->value"] = [ColumnDataType::Boolean, $condition, true, [true, false], 1];
        }

        return $testCases;
    }

    /**
     * @throws InvalidRow
     * @throws RowAlreadyExists
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testGetMaxValueInColumn(): void
    {
        $columnDefinitions = [
            (new ColumnDefinition('id', ColumnDataType::Id))->withDbColumn('idx'),
            (new ColumnDefinition('name', ColumnDataType::Text))->withRequired(true),
            (new ColumnDefinition('age', ColumnDataType::Integer))->withDbColumn('edad'),
        ];
        $table = $this->getTestTable($columnDefinitions);

        $this->assertSame(null, $table->getMaxValueInColumn('age'));

        $table->createRow(['name' => 'John', 'age' => 30]);
        $table->createRow(['name' => 'Jane', 'age' => 20]);
        $table->createRow(['name' => 'Joe', 'age' => 45]);

        $this->assertSame(45, $table->getMaxValueInColumn('age'));
        $this->assertSame(3, $table->getMaxValueInColumn('id'));
    }


    #[Test]
    public function testGetMaxValueInColumnRejectsUnknownColumn(): void
    {
        $table = $this->getTestTable([
            new ColumnDefinition('id', ColumnDataType::Id),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Column missing not found');
        $table->getMaxValueInColumn('missing');
    }

    #[Test]
    public function testGetMaxValueInColumnRejectsNonNumericColumn(): void
    {
        $table = $this->getTestTable([
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition('name', ColumnDataType::Text))->withRequired(true),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Column name is not numeric');
        $table->getMaxValueInColumn('name');
    }

    /**
     * @throws InvalidRow
     * @throws RowAlreadyExists|RandomException
     */
    #[Test]
    public function testDeleteRow(): void
    {
        $optionalColumnDefinitions = $this->getOptionalColumnDefinitions();
        $table = $this->getTestTable(array_merge([
            (new ColumnDefinition('id', ColumnDataType::Id))->withDbColumn('idx'),
            (new ColumnDefinition('name', ColumnDataType::Text))->withRequired(true),
        ], $optionalColumnDefinitions));
        $rowId = $table->createRow(array_merge(
            ['name' => 'John'],
            $this->getSampleValues($optionalColumnDefinitions),
        ));

        $this->assertSame(1, $table->deleteRow($rowId));
        $this->assertFalse($table->rowExists($rowId));
        $this->assertNull($table->getRow($rowId));
        $this->assertSame(0, $table->deleteRow($rowId));
    }

    /**
     * @throws InvalidRow
     * @throws RowAlreadyExists|RandomException
     */
    #[Test]
    public function testUpdateRow(): void
    {
        $optionalColumnDefinitions = $this->getOptionalColumnDefinitions();
        $table = $this->getTestTable(array_merge([
            (new ColumnDefinition('id', ColumnDataType::Id))->withDbColumn('idx'),
            (new ColumnDefinition('name', ColumnDataType::Text))->withDbColumn('nombre')->withRequired(true),
            (new ColumnDefinition('age', ColumnDataType::Integer))->withDbColumn('edad'),
        ], $optionalColumnDefinitions));

        $optionalValues = $this->getSampleValues($optionalColumnDefinitions);

        $original = array_merge(
            ['name' => 'John', 'age' => 30],
            $optionalValues,
        );

        $rowId = $table->createRow($original);

        $updatedRow = array_merge(
            ['id' => $rowId, 'name' => 'Jane', 'age' => 35],
            $optionalValues,
        );
        $table->updateRow($updatedRow);

        $this->assertSame($updatedRow, $table->getRow($rowId));
    }

    /**
     * @throws InvalidRow
     */
    #[Test]
    public function testUpdateRowRejectsInvalidInput(): void
    {
        $table = $this->getTestTable([
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition('name', ColumnDataType::Text))->withRequired(true),
        ]);

        $this->expectException(InvalidRow::class);
        $this->expectExceptionMessage("Column 'unknown' is not defined in the schema");
        $table->updateRow(['id' => 1, 'name' => 'whatever', 'unknown' => 'value']);
    }

    /**
     * @throws InvalidRow
     */
    #[Test]
    public function testUpdateRowRejectsInvalidRowForUpdate(): void
    {
        $table = $this->getTestTable([
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition('name', ColumnDataType::Text))->withRequired(true),
        ]);

        $this->expectException(InvalidRow::class);
        $this->expectExceptionMessage("Id not set in given row");
        $table->updateRow(['name' => 'Jane']);
    }

    /**
     */
    #[Test]
    public function testArrayAccess(): void
    {
        $table = $this->getTestTable([
            (new ColumnDefinition('id', ColumnDataType::Id))->withDbColumn('idx'),
            (new ColumnDefinition('name', ColumnDataType::Text))->withDbColumn('nombre')->withRequired(true),
            (new ColumnDefinition('age', ColumnDataType::Integer))->withDbColumn('edad'),
        ]);

        $table[] = ['name' => 'John', 'age' => 30];
        $rowId = 1;

        $this->assertSame([
            'id' => $rowId,
            'name' => 'John',
            'age' => 30,
        ], $table[$rowId]);

        $table[$rowId] = ['name' => 'Jane', 'age' => 35];

        $this->assertSame([
            'id' => $rowId,
            'name' => 'Jane',
            'age' => 35,
        ], $table[$rowId]);

        unset($table[$rowId]);

        $this->assertArrayNotHasKey($rowId, $table);
        $this->assertNull($table[$rowId]);

        $table[25] = ['name' => 'Peter', 'age' => 45];
        $this->assertSame([
            'id' => 25,
            'name' => 'Peter',
            'age' => 45,
        ], $table[25]);
    }

    /**
     * @param array<string, mixed> $badRow
     * @throws RowAlreadyExists
     */
    #[Test]
    #[DataProvider('badRowProvider')]
    public function testBadRows(array $badRow): void
    {
        $columnDefinitions = [
            (new ColumnDefinition('id', ColumnDataType::Id))->withDbColumn('idx'),
            (new ColumnDefinition('name', ColumnDataType::Text))->withDbColumn('nombre')->withRequired(true),
            (new ColumnDefinition('age', ColumnDataType::Integer))->withDbColumn('edad')->withNullable(true),
            (new ColumnDefinition('active', ColumnDataType::Boolean))->withDbColumn('activo')->withRequired(true),
        ];
        $table = $this->getTestTable($columnDefinitions);
        $this->expectException(InvalidRow::class);
        $table->createRow($badRow);
    }

    public static function badRowProvider(): \Iterator
    {
        yield 'missing required key' => [['name' => 'John']];
        yield 'bad Name' => [['name' => 123, 'active' => true]];
        yield 'bad Age' => [['name' => 'John', 'age' => '30', 'active' => true]];
        yield 'bad Boolean' => [['name' => 'John', 'active' => 'true']];
    }

    /**
     * @param array<ColumnDefinition> $columnDefinitions
     * @return array<int, array<string, mixed>>
     * @throws RandomException
     */
    private function makeFakeValidRows(array $columnDefinitions, int $numRows): array
    {
        $rows = [];
        for ($i = 0; $i < $numRows; $i++) {
            $row = [];
            foreach ($columnDefinitions as $columnDefinition) {
                if (!$columnDefinition->required && $this->getRandomBool()) {
                    continue;
                }
                if ($columnDefinition->nullable && $this->getRandomBool()) {
                    $row[$columnDefinition->rowKey] = null;
                    continue;
                }
                switch ($columnDefinition->type) {
                    case ColumnDataType::Text:
                        $row[$columnDefinition->rowKey] = $this->getRandomString(1024);
                        break;
                    case ColumnDataType::Integer:
                        $row[$columnDefinition->rowKey] = random_int(0, 1000);
                        break;

                    case ColumnDataType::TimeString:
                        $row[$columnDefinition->rowKey] = TimeString::now();
                        break;

                    case ColumnDataType::Boolean:
                        $row[$columnDefinition->rowKey] = $this->getRandomBool();
                        break;
                    case ColumnDataType::Serializable:
                        $row[$columnDefinition->rowKey] = [
                            'num' => random_int(0, 1000),
                            'str' => $this->getRandomString(1024),
                        ];
                        break;
                    case ColumnDataType::VarChar:
                        $row[$columnDefinition->rowKey] = $this->getRandomString($columnDefinition->typeLength);
                        break;
                    case ColumnDataType::Id:
                        break;
                }
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * @throws RandomException
     */
    private function getRandomString(int $maxLength): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $length = random_int(1, $maxLength);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    /**
     * @throws RandomException
     */
    private function getRandomBool(): bool
    {
        return random_int(0, 1) === 1;
    }

    /**
     * @return array<ColumnDefinition>
     * @throws RandomException
     */
    private function getOptionalColumnDefinitions(): array
    {
        $table = $this->getTestTable([
            new ColumnDefinition('id', ColumnDataType::Id),
        ]);
        $supportedDataTypes = $table->getSupportedDataTypes();
        $columnDefinitions = [];

        if (in_array(ColumnDataType::VarChar, $supportedDataTypes, true)) {
            $columnDefinitions[] = (new ColumnDefinition('description', ColumnDataType::VarChar))->withTypeLength(random_int(8, 512));
        }
        if (in_array(ColumnDataType::Serializable, $supportedDataTypes, true)) {
            $columnDefinitions[] = (new ColumnDefinition('metadata', ColumnDataType::Serializable))->withRequired(true);
        }

        if (in_array(ColumnDataType::TimeString, $supportedDataTypes, true)) {
            $columnDefinitions[] = (new ColumnDefinition('time', ColumnDataType::TimeString));
        }

        return $columnDefinitions;
    }

    /**
     * @param array<ColumnDefinition> $columnDefinitions
     * @return array<string, mixed>
     * @throws RandomException
     */
    private function getSampleValues(array $columnDefinitions): array
    {
        $values = [];
        foreach ($columnDefinitions as $columnDefinition) {
            $values[$columnDefinition->rowKey] = match ($columnDefinition->type) {
                ColumnDataType::Serializable => ['key' => 'value'],
                ColumnDataType::VarChar => $this->getRandomString($columnDefinition->typeLength),
                ColumnDataType::TimeString => TimeString::now(),
                default => throw new LogicException("Unexpected optional column type '{$columnDefinition->type->value}'"),
            };
        }

        return $values;
    }
}
