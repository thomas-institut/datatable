<?php

namespace ThomasInstitut\DataTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\RandomException;
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
        $table = new GenericDataTableWithSchema(new InMemoryDataTable(), $columDefs, new StringValuesDbRowValueTranslator());

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
        $table = new GenericDataTableWithSchema(new InMemoryDataTable(), $columDefs, new StringValuesDbRowValueTranslator());
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