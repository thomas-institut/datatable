<?php

namespace ThomasInstitut\DataTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\RandomException;
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\Exception\InvalidRow;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\ColumnDefinition;
use ThomasInstitut\DataTable\Schema\StringValuesDbRowValueTranslator;

#[CoversClass(GenericDataTableWithSchema::class)]
class GenericDataTableWithSchemaTest extends TestCase
{


    /**
     * @throws InvalidColumnDefinitionsArray
     */
    public function getTestTable(array $columnDefs): GenericDataTableWithSchema
    {

        return new GenericDataTableWithSchema(new InMemoryDataTable(), $columnDefs, new StringValuesDbRowValueTranslator());
    }

    /**
     * @throws InvalidColumnDefinitionsArray
     * @throws RowAlreadyExists|InvalidRow|RandomException
     */
    #[Test]
    public function testBasic(): void
    {
        $columDefs = [
            (new ColumnDefinition('id', ColumnDataType::Id))->withDbColumn('idx'),
            (new ColumnDefinition('name', ColumnDataType::Text))->withDbColumn('nombre')->withRequired(true),
            (new ColumnDefinition('description', ColumnDataType::VarChar))->withTypeLength(255),
            (new ColumnDefinition('age', ColumnDataType::Integer))->withDbColumn('edad'),
            new ColumnDefinition('metadata', ColumnDataType::Any),
            (new ColumnDefinition('active', ColumnDataType::Boolean))->withRequired(true),
        ];
        $table = $this->getTestTable($columDefs);

        $numRows = 100;
        $rowsToTest = $this->makeFakeValidRows($columDefs, $numRows);

        $rowIdMap = array_map(function ($row) use ($table) {
            return $table->createRow($row);
        }, $rowsToTest);

        $createdIds = array_values($rowIdMap);
        sort($createdIds);
        $this->assertSame($createdIds, iterator_to_array($table->getUniqueIds()));

        $allRows = $table->getAllRows();
        $this->assertEquals($numRows, $allRows->count());

        $this->assertNull($table->getRow(999999999));
        $this->assertFalse($table->rowExists(8888888));

        foreach ($rowIdMap as $index => $id) {
            $this->assertTrue($table->rowExists($id));
            $fetchedRow = $table->getRow($id);
            $originalRow = $rowsToTest[$index];
            foreach ($originalRow as $columnName => $value) {
                $this->assertEquals($value, $fetchedRow[$columnName]);
            }
        }

    }

    /**
     * @throws InvalidColumnDefinitionsArray
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

        $this->assertSame(1, $rows->count());
        $this->assertSame([
            'id' => $johnId,
            'name' => 'John',
            'age' => 30,
        ], $rows->getFirst());

        $this->assertSame(1, $table->findRows(['age' => 30], 1)->count());
        $this->assertSame(0, $table->findRows(['name' => 'Missing'])->count());
        $this->assertSame($janeId, $table->findRows(['name' => 'Jane'])->getFirst()['id']);
    }

    /**
     * @throws InvalidColumnDefinitionsArray
     * @throws InvalidRow
     * @throws RowAlreadyExists
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testGetMaxValueInColumn(): void
    {
        $columnDefs = [
            (new ColumnDefinition('id', ColumnDataType::Id))->withDbColumn('idx'),
            (new ColumnDefinition('name', ColumnDataType::Text))->withRequired(true),
            (new ColumnDefinition('age', ColumnDataType::Integer))->withDbColumn('edad'),
        ];
        $table = $this->getTestTable($columnDefs);

        $this->assertSame(0, $table->getMaxValueInColumn('age'));

        $table->createRow(['name' => 'John', 'age' => 30]);
        $table->createRow(['name' => 'Jane', 'age' => 20]);
        $table->createRow(['name' => 'Joe', 'age' => 45]);

        $this->assertSame(45, $table->getMaxValueInColumn('age'));
        $this->assertSame(3, $table->getMaxValueInColumn('id'));
    }

    /**
     * @throws InvalidColumnDefinitionsArray
     */
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

    /**
     * @throws InvalidColumnDefinitionsArray
     */
    #[Test]
    public function testGetMaxValueInColumnRejectsNonNumericColumn(): void
    {
        $table = $this->getTestTable([
            new ColumnDefinition('id', ColumnDataType::Id),
            new ColumnDefinition('name', ColumnDataType::Text),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Column name is not numeric');
        $table->getMaxValueInColumn('name');
    }

    /**
     * @throws InvalidColumnDefinitionsArray
     * @throws InvalidRow
     * @throws RowAlreadyExists
     */
    #[Test]
    public function testDeleteRow(): void
    {
        $table = $this->getTestTable([
            (new ColumnDefinition('id', ColumnDataType::Id))->withDbColumn('idx'),
            (new ColumnDefinition('name', ColumnDataType::Text))->withRequired(true),
        ]);
        $rowId = $table->createRow(['name' => 'John']);

        $this->assertSame(1, $table->deleteRow($rowId));
        $this->assertFalse($table->rowExists($rowId));
        $this->assertNull($table->getRow($rowId));
        $this->assertSame(0, $table->deleteRow($rowId));
    }

    /**
     * @throws InvalidColumnDefinitionsArray
     * @throws InvalidRow
     * @throws RowAlreadyExists
     */
    #[Test]
    public function testUpdateRow(): void
    {
        $table = new GenericDataTableWithSchema(new InMemoryDataTable(), [
            (new ColumnDefinition('id', ColumnDataType::Id))->withDbColumn('idx'),
            (new ColumnDefinition('name', ColumnDataType::Text))->withDbColumn('nombre')->withRequired(true),
            (new ColumnDefinition('age', ColumnDataType::Integer))->withDbColumn('edad'),
        ]);
        $rowId = $table->createRow(['name' => 'John', 'age' => 30]);

        $table->updateRow(['id' => $rowId, 'name' => 'Jane', 'age' => 35]);

        $this->assertSame([
            'id' => $rowId,
            'name' => 'Jane',
            'age' => 35,
        ], $table->getRow($rowId));
    }

    /**
     * @throws InvalidColumnDefinitionsArray
     * @throws InvalidRow
     */
    #[Test]
    public function testUpdateRowRejectsInvalidInput(): void
    {
        $table = $this->getTestTable([
            new ColumnDefinition('id', ColumnDataType::Id),
            new ColumnDefinition('name', ColumnDataType::Text),
        ]);

        $this->expectException(InvalidRow::class);
        $this->expectExceptionMessage("Column 'unknown' is not defined in the schema");
        $table->updateRow(['id' => 1, 'unknown' => 'value']);
    }

    /**
     * @throws InvalidColumnDefinitionsArray
     * @throws InvalidRow
     */
    #[Test]
    public function testUpdateRowRejectsInvalidRowForUpdate(): void
    {
        $table = $this->getTestTable([
            new ColumnDefinition('id', ColumnDataType::Id),
            new ColumnDefinition('name', ColumnDataType::Text),
        ]);
        $this->expectException(InvalidRow::class);
        $this->expectExceptionMessage("Id not set in given row (DataTable updateRow)");
        $table->updateRow(['name' => 'Jane']);
    }

    /**
     * @throws InvalidColumnDefinitionsArray
     */
    #[Test]
    public function testArrayAccess(): void
    {
        $table = new GenericDataTableWithSchema(new InMemoryDataTable(), [
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

        $this->assertFalse(isset($table[$rowId]));
        $this->assertNull($table[$rowId]);

        $table[25] = ['name' => 'Peter', 'age' => 45];
        $this->assertSame([
            'id' => 25,
            'name' => 'Peter',
            'age' => 45,
        ], $table[25]);

    }

    /**
     * @throws InvalidColumnDefinitionsArray
     * @throws RowAlreadyExists
     */
    #[Test]
    #[DataProvider('badRowProvider')]
    public function testBadRows(array $badRow): void
    {
        $columDefs = [
            (new ColumnDefinition('id', ColumnDataType::Id))->withDbColumn('idx'),
            (new ColumnDefinition('name', ColumnDataType::Text))->withDbColumn('nombre')->withRequired(true),
            (new ColumnDefinition('someText', ColumnDataType::VarChar))->withTypeLength(10),
            (new ColumnDefinition('age', ColumnDataType::Integer))->withDbColumn('edad')->withNullable(true),
            (new ColumnDefinition('active', ColumnDataType::Boolean))->withDbColumn('activo')->withRequired(true),
        ];
        $table = $this->getTestTable($columDefs);
        $this->expectException(InvalidRow::class);
        $table->createRow($badRow);
    }

    public static function badRowProvider(): array
    {
        return [
            'missing required key' => [['name' => 'John']],
            'bad Name' => [['name' => 123, 'active' => true]],
            'bad Age' => [['name' => 'John', 'age' => '30', 'active' => true]],
            'bad Boolean' => [['name' => 'John', 'active' => 'true']],
            'bad text' => [['name' => 'John', 'someText' => true, 'active' => true]],
            'long text' => [['name' => 'John', 'someText' => 'this is a long text that is more than 10 characters long', 'active' => true]],
        ];
    }

    /**
     * @param array<ColumnDefinition> $columnDefinitions
     * @param int $numRows
     * @return array
     * @throws RandomException
     */
    private function makeFakeValidRows(array $columnDefinitions, int $numRows): array
    {
        $rows = [];
        for ($i = 0; $i < $numRows; $i++) {
            $row = [];
            foreach ($columnDefinitions as $colDef) {
                if (!$colDef->required) {
                    if ($this->getRandomBool()) {
                        continue;
                    }
                }
                if ($colDef->nullable && $this->getRandomBool()) {
                    $row[$colDef->rowKey] = null;
                    continue;
                }
                switch ($colDef->type) {
                    case ColumnDataType::Text:
                        $row[$colDef->rowKey] = $this->getRandomString(1024);
                        break;
                    case ColumnDataType::Integer:
                        $row[$colDef->rowKey] = random_int(0, 1000);
                        break;
                    case ColumnDataType::Boolean:
                        $row[$colDef->rowKey] = $this->getRandomBool();
                        break;

                    case ColumnDataType::Any:
                        $someObject = ['num' => random_int(0, 1000), 'str' => $this->getRandomString(1024)];
                        $row[$colDef->rowKey] = $someObject;
                        break;

                    case ColumnDataType::VarChar:
                        $row[$colDef->rowKey] = $this->getRandomString($colDef->typeLength);
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


}