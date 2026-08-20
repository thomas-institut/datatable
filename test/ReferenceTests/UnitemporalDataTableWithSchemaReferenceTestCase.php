<?php
declare(strict_types=1);

namespace ThomasInstitut\DataTable\ReferenceTests;

use PHPUnit\Framework\Attributes\Test;
use ThomasInstitut\DataTable\DataTableWithSchema;
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\Exception\InvalidRow;
use ThomasInstitut\DataTable\Exception\InvalidRowUpdateTime;
use ThomasInstitut\DataTable\Exception\InvalidTimeStringException;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\Exception\RowDoesNotExist;
use ThomasInstitut\DataTable\ResultsIterator\ResultsIterator;
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\ColumnDefinition;
use ThomasInstitut\DataTable\SearchCondition;
use ThomasInstitut\DataTable\SearchSpec;
use ThomasInstitut\DataTable\SearchType;
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
     * @throws InvalidColumnDefinitionsArray
     */
    private function getTestUnitemporalTable(): UnitemporalDataTableWithSchema
    {
        return $this->getUnitemporalTestTable([
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition('name', ColumnDataType::Text))->withRequired(true),
            new ColumnDefinition('validFrom', ColumnDataType::ValidFrom),
            new ColumnDefinition('validUntil', ColumnDataType::ValidUntil),
        ]);
    }

    private function assertInvalidTimeString(callable $operation): void
    {
        try {
            $operation();
            $this->fail('An invalid time string must be rejected.');
        } catch (InvalidTimeStringException) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * @throws InvalidTimeStringException
     * @throws InvalidTimeZoneException
     * @throws RowAlreadyExists
     * @throws InvalidRow
     * @throws InvalidTimeString|InvalidColumnDefinitionsArray
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testUnitemporalBasic(): void
    {
        /** @var array<ColumnDefinition> $defs */
        $defs = [
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition('name', ColumnDataType::Text))->withRequired(true),
            (new ColumnDefinition('age', ColumnDataType::Integer))->withDbColumn('edad')->withRequired(true),
            (new ColumnDefinition('salary', ColumnDataType::Integer))->withDefaultValue(1000),
            (new ColumnDefinition('validFrom', ColumnDataType::ValidFrom))->withDbColumn('valido_desde'),
            (new ColumnDefinition('validUntil', ColumnDataType::ValidUntil))->withDbColumn('valido_hasta'),
        ];

        $table = $this->getUnitemporalTestTable($defs);

        $refTimestamp = time();

        $testRows = [
            ['John', 19],
            ['Jennifer', 20, 20000],
            ['Paul', 24],
            ['Penelope', 30, 20000],
        ];
        $testRowCount = count($testRows);

        $ids = [];
        foreach ($testRows as $index => $row) {
            $theRow = ['name' => $row[0], 'age' => $row[1]];
            if (isset($row[2])) {
                $theRow['salary'] = $row[2];
            }
            $ids[] = $table->createRowWithTime($theRow, TimeString::fromTimeStamp($refTimestamp + $index));
        }

        foreach ($ids as $index => $id) {
            $row = $table->getRowWithTime($id, TimeString::fromTimeStamp($refTimestamp + $testRowCount));
            $this->assertNotNull($row);
            $this->assertEquals($testRows[$index][0], $row['name']);
            $this->assertEquals($testRows[$index][1], $row['age']);
            if (isset($testRows[$index][2])) {
                $this->assertEquals($testRows[$index][2], $row['salary']);
            } else {
                $this->assertEquals(1000, $row['salary']);
            }
            $this->assertSame($refTimestamp + $index, intval(TimeString::toTimeStamp($row['validFrom'])));
            $this->assertEquals(TimeString::END_OF_TIMES, $row['validUntil']);
            $this->assertNull($table->getRowWithTime($id, TimeString::fromTimeStamp($refTimestamp + $index - 1)));
        }

    }

    /**
     * @throws InvalidColumnDefinitionsArray
     * @throws InvalidRow
     * @throws RowAlreadyExists
     * @throws InvalidTimeStringException
     */
    #[Test]
    public function testTimeStringMethodsRejectInvalidTimeStrings(): void
    {
        $table = $this->getTestUnitemporalTable();
        $rowId = $table->createRowWithTime(['name' => 'John'], '2010-01-01');
        $invalidTimeString = 'not-a-time-string';

        $this->assertInvalidTimeString(
            fn(): int => $table->createRowWithTime(['name' => 'Jane'], $invalidTimeString)
        );
        $this->assertInvalidTimeString(
            fn(): bool => $table->rowExistsWithTime($rowId, $invalidTimeString)
        );
        $this->assertInvalidTimeString(
            fn(): ?array => $table->getRowWithTime($rowId, $invalidTimeString)
        );
        $this->assertInvalidTimeString(
            fn(): ResultsIterator => $table->findRowsWithTime(['name' => 'John'], 0, $invalidTimeString)
        );
        $this->assertInvalidTimeString(
            fn(): ResultsIterator => $table->searchWithTime(
                [new SearchSpec('name', SearchCondition::Equals, 'John')],
                SearchType::And,
                $invalidTimeString,
            )
        );
        $this->assertInvalidTimeString(
            fn() => $table->updateRowWithTime(['id' => $rowId, 'name' => 'Jane'], $invalidTimeString)
        );
        $this->assertInvalidTimeString(
            fn(): int => $table->deleteRowWithTime($rowId, $invalidTimeString)
        );
    }

    /**
     * @throws InvalidColumnDefinitionsArray
     * @throws InvalidTimeStringException|RowAlreadyExists
     */
    #[Test]
    public function testCreateRowWithTimeRejectsInvalidRow(): void
    {
        $table = $this->getTestUnitemporalTable();

        $this->expectException(InvalidRow::class);
        $table->createRowWithTime(['name' => 'John', 'unknown' => 'value'], '2010-01-01');
    }

    /**
     * @throws InvalidColumnDefinitionsArray|InvalidTimeStringException
     */
    #[Test]
    public function testFindRowsWithTimeRejectsInvalidRow(): void
    {
        $table = $this->getTestUnitemporalTable();

        $this->expectException(InvalidRow::class);
        $table->findRowsWithTime(['unknown' => 'value'], 0, '2010-01-01');
    }

    /**
     * @throws InvalidColumnDefinitionsArray|RowDoesNotExist|InvalidTimeStringException|InvalidRowUpdateTime
     * /
     */
    #[Test]
    public function testUpdateRowWithTimeRejectsInvalidRow(): void
    {
        $table = $this->getTestUnitemporalTable();

        $this->expectException(InvalidRow::class);
        $table->updateRowWithTime(['id' => 1, 'name' => 'John', 'unknown' => 'value'], '2010-01-01');
    }

    /**
     * @throws InvalidColumnDefinitionsArray
     * @throws InvalidRow
     * @throws RowAlreadyExists|InvalidTimeStringException|RowDoesNotExist
     *
     */
    #[Test]
    public function testUpdateRowWithTimeRejectsInvalidUpdateTime(): void
    {
        $table = $this->getTestUnitemporalTable();
        $rowId = $table->createRowWithTime(['name' => 'John'], '2010-01-01');

        $this->expectException(InvalidRowUpdateTime::class);
        $table->updateRowWithTime(['id' => $rowId, 'name' => 'Jane'], '2010-01-01');
    }

    /**
     * @throws InvalidColumnDefinitionsArray
     * @throws InvalidRow
     * @throws RowAlreadyExists|InvalidTimeStringException
     */
    #[Test]
    public function testDeleteRowWithTimeRejectsInvalidUpdateTime(): void
    {
        $table = $this->getTestUnitemporalTable();
        $rowId = $table->createRowWithTime(['name' => 'John'], '2010-01-01');

        $this->expectException(InvalidRowUpdateTime::class);
        $table->deleteRowWithTime($rowId, '2010-01-01');
    }
}