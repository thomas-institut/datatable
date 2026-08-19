<?php
declare(strict_types=1);

namespace ThomasInstitut\DataTable\ReferenceTests;

use PHPUnit\Framework\Attributes\Test;
use ThomasInstitut\DataTable\DataTableWithSchema;
use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\Exception\InvalidRow;
use ThomasInstitut\DataTable\Exception\InvalidTimeStringException;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\ColumnDefinition;
use ThomasInstitut\DataTable\UnitemporalDataTableWithSchema;
use ThomasInstitut\TimeString\InvalidTimeString;
use ThomasInstitut\TimeString\InvalidTimeZoneException;
use ThomasInstitut\TimeString\TimeString;

abstract class UnitemporalDataTableWithSchemaReferenceTestCase extends DataTableWithSchemaReferenceTestCase
{

    /**
     * @param array<ColumnDefinition> $columnDefinitions
     * @throws InvalidColumnDefinitionsArray
     */
    abstract public function getUnitemporalTestTable(array $columnDefinitions): UnitemporalDataTableWithSchema;

    /**
     * @inheritDoc
     */
    public function getTestTable(array $columnDefinitions): DataTableWithSchema
    {
        return $this->getUnitemporalTestTable($columnDefinitions);
    }

    /**
     * @throws InvalidTimeStringException
     * @throws InvalidTimeZoneException
     * @throws RowAlreadyExists
     * @throws InvalidRow
     * @throws InvalidTimeString|InvalidColumnDefinitionsArray
     */
    #[Test]
    public function testUnitemporalBasic(): void
    {
        /** @var array<ColumnDefinition> $defs */
        $defs = [
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition('name', ColumnDataType::Text))->withRequired(true),
            (new ColumnDefinition('age', ColumnDataType::Integer))->withDbColumn('edad'),
            (new ColumnDefinition('validFrom', ColumnDataType::ValidFrom))->withDbColumn('valido_desde'),
            (new ColumnDefinition('validUntil', ColumnDataType::ValidUntil))->withDbColumn('valido_hasta'),
        ];

        $table = $this->getUnitemporalTestTable($defs);

        $refTimestamp = time();

        $testRows = [
            ['John', 19],
            ['Jane', 20],
            ['Paul', 24],
            ['Penny', 30],
        ];
        $testRowCount = count($testRows);

        $ids = [];
        foreach ($testRows as $index => $row) {
            $theRow = ['name' => $row[0], 'age' => $row[1]];
            $ids[] = $table->createRowWithTime($theRow, TimeString::fromTimeStamp($refTimestamp + $index));
        }

        foreach ($ids as $index => $id) {
            $row = $table->getRowWithTime($id, TimeString::fromTimeStamp($refTimestamp + $testRowCount));
            $this->assertNotNull($row);
            $this->assertEquals($testRows[$index][0], $row['name']);
            $this->assertEquals($testRows[$index][1], $row['age']);
            $this->assertSame($refTimestamp + $index, intval(TimeString::toTimeStamp($row['validFrom'])));
            $this->assertEquals(TimeString::END_OF_TIMES, $row['validUntil']);
            $this->assertNull($table->getRowWithTime($id, TimeString::fromTimeStamp($refTimestamp + $index - 1)));
        }

    }
}