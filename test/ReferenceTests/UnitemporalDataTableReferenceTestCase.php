<?php

namespace ThomasInstitut\DataTable\ReferenceTests;

use PHPUnit\Framework\Attributes\Test;
use ThomasInstitut\DataTable\DataTable;
use ThomasInstitut\DataTable\Exception\InvalidRowForUpdate;
use ThomasInstitut\DataTable\Exception\InvalidRowUpdateTime;
use ThomasInstitut\DataTable\Exception\InvalidSearchSpec;
use ThomasInstitut\DataTable\Exception\InvalidSearchType;
use ThomasInstitut\DataTable\Exception\InvalidTimeStringException;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\Exception\RowDoesNotExist;
use ThomasInstitut\DataTable\ResultsIterator\ResultsIterator;
use ThomasInstitut\DataTable\UnitemporalDataTable;
use ThomasInstitut\TimeString\InvalidTimeZoneException;
use ThomasInstitut\TimeString\TimeString;

abstract class UnitemporalDataTableReferenceTestCase extends DataTableReferenceTestCase
{

    abstract public function getTestUnitemporalDataTable(bool $resetTable = true, bool $newSession = false): UnitemporalDataTable;

    public function getTestDataTable(bool $resetTable = true, bool $newSession = false): DataTable
    {
        return $this->getTestUnitemporalDataTable($resetTable, $newSession);
    }


    /**
     * @throws InvalidTimeStringException
     * @throws InvalidTimeZoneException
     * @throws RowAlreadyExists
     */
    #[Test]
    public function testCreateRowWithTime(): void
    {
        $table = $this->getTestUnitemporalDataTable();
        $referenceTimestamp = time();
        $colName = DataTableReferenceTestCase::STRING_COLUMN;
        $idCol = $table->getIdColumnName();
        $validUntilCol = UnitemporalDataTable::DEFAULT_VALID_UNTIL_COLUMN;
        $validFromCol = UnitemporalDataTable::DEFAULT_VALID_FROM_COLUMN;

        $table->createRowWithTime([$colName => 'Jane'], TimeString::fromTimestamp($referenceTimestamp));
        $table->createRowWithTime([$colName => 'George'], TimeString::fromTimestamp($referenceTimestamp+1));
        $table->createRowWithTime([$colName => 'Mary'], TimeString::fromTimestamp($referenceTimestamp+1));
        $rowId = $table->createRowWithTime([$colName => 'John'], TimeString::fromTimestamp($referenceTimestamp));

        $this->assertTrue($table->rowExistsWithTime($rowId, TimeString::fromTimestamp($referenceTimestamp + 1)));
        $retrievedRow = $table->getRowWithTime($rowId, TimeString::fromTimestamp($referenceTimestamp + 1));
        $this->assertEquals($rowId, $retrievedRow[$idCol]);
        $this->assertEquals('John', $retrievedRow[$colName]);
        $this->assertEquals(TimeString::fromTimestamp($referenceTimestamp), $retrievedRow[$validFromCol]);
        $this->assertEquals(TimeString::END_OF_TIMES, $retrievedRow[$validUntilCol]);
        $this->assertFalse($table->rowExistsWithTime($rowId, TimeString::fromTimestamp($referenceTimestamp - 1)));
        $this->assertNull($table->getRowWithTime($rowId, TimeString::fromTimestamp($referenceTimestamp - 1)));
    }

    /**
     * @throws InvalidTimeStringException
     * @throws RowAlreadyExists
     */
    #[Test]
    public function testCreateRowWithTimeRejectsInvalidAndDuplicateRows(): void
    {
        $table = $this->getTestUnitemporalDataTable();
        $idColumn = $table->getIdColumnName();
        $time = '2010-01-01 00:00:00';

        try {
            $table->createRowWithTime([$idColumn => 1], 'BadTime');
            $this->fail('An invalid time must be rejected.');
        } catch (InvalidTimeStringException) {
            $this->assertSame(UnitemporalDataTable::ERROR_INVALID_TIME, $table->getErrorCode());
        }

        $this->assertSame(1, $table->createRowWithTime([$idColumn => 1, self::STRING_COLUMN => 'first'], $time));
        try {
            $table->createRowWithTime([$idColumn => 1, self::STRING_COLUMN => 'second'], $time);
            $this->fail('A duplicate row must be rejected.');
        } catch (RowAlreadyExists) {
            $this->assertSame(DataTable::ERROR_ROW_ALREADY_EXISTS, $table->getErrorCode());
        }

        $row = $table->getRowWithTime(1, $time);
        $this->assertNotNull($row);
        $this->assertSame('first', $row[self::STRING_COLUMN]);
    }

    /**
     * @throws InvalidRowUpdateTime
     * @throws InvalidRowForUpdate
     * @throws RowDoesNotExist
     * @throws InvalidTimeStringException
     * @throws RowAlreadyExists
     */
    #[Test]
    public function testGetRowWithTimeAndRowExistsWithHistory(): void
    {
        $table = $this->getTestUnitemporalDataTable();
        $rowId = $table->createRowWithTime([self::STRING_COLUMN => 'first'], '2010-01-01');
        $table->updateRowWithTime([
            $table->getIdColumnName() => $rowId,
            self::STRING_COLUMN => 'second',
        ], '2015-01-01');

        $this->assertFalse($table->rowExistsWithTime($rowId, '2009-12-31'));
        $this->assertNull($table->getRowWithTime($rowId, '2009-12-31'));
        $this->assertTrue($table->rowExistsWithTime($rowId, '2010-01-01'));
        $this->assertSame('first', $table->getRowWithTime($rowId, '2014-12-31')[self::STRING_COLUMN]);
        $this->assertSame('second', $table->getRowWithTime($rowId, '2015-01-01')[self::STRING_COLUMN]);
    }

    /**
     * @throws InvalidRowUpdateTime
     * @throws InvalidRowForUpdate
     * @throws RowDoesNotExist
     * @throws InvalidTimeStringException
     * @throws RowAlreadyExists
     */
    #[Test]
    public function testFindRowsWithTime(): void
    {
        $table = $this->getTestUnitemporalDataTable();
        $firstId = $table->createRowWithTime([self::INT_COLUMN => 1, self::STRING_COLUMN => 'old'], '2010-01-01');
        $table->createRowWithTime([self::INT_COLUMN => 2, self::STRING_COLUMN => 'other'], '2010-01-01');
        $table->updateRowWithTime([
            $table->getIdColumnName() => $firstId,
            self::INT_COLUMN => 1,
            self::STRING_COLUMN => 'new',
        ], '2015-01-01');

        $this->assertSame(1, $table->findRowsWithTime([self::STRING_COLUMN => 'old'], 0, '2014-12-31')->count());
        $this->assertSame(1, $table->findRowsWithTime([self::STRING_COLUMN => 'new'], 0, '2015-01-01')->count());
        $this->assertSame(0, $table->findRowsWithTime([self::STRING_COLUMN => 'old'], 0, '2015-01-01')->count());
        // The PDO reference cannot build a SQL predicate for an empty row,
        // so both reference implementations return no rows for this input.
        $this->assertSame(0, $table->findRowsWithTime([], 1, '2015-01-01')->count());
    }

    /**
     * @throws InvalidTimeStringException
     * @throws InvalidSearchSpec
     * @throws RowAlreadyExists
     * @throws InvalidSearchType
     */
    #[Test]
    public function testSearchWithTime(): void
    {
        $table = $this->getTestUnitemporalDataTable();
        $firstId = $table->createRowWithTime([self::STRING_COLUMN => 'matching'], '2010-01-01');
        $secondId = $table->createRowWithTime([self::STRING_COLUMN => 'matching'], '2010-01-01');
        $thirdId = $table->createRowWithTime([self::STRING_COLUMN => 'other'], '2010-01-01');

        $matchingSearch = [
            ['column' => self::STRING_COLUMN, 'condition' => DataTable::COND_EQUAL_TO, 'value' => 'matching'],
        ];

        $table->deleteRowWithTime($secondId, '2015-01-01');
        $table->deleteRowWithTime($firstId, '2020-01-01');

        $idColumn = $table->getIdColumnName();
        $getResultIds = static function (ResultsIterator $results) use ($idColumn): array {
            $ids = [];
            foreach ($results as $row) {
                $ids[] = $row[$idColumn];
            }
            return $ids;
        };

        $this->assertSame([$firstId, $secondId], $getResultIds($table->searchWithTime(
            $matchingSearch,
            DataTable::SEARCH_AND,
            '2014-12-31'
        )));
        $this->assertSame([$firstId], $getResultIds($table->searchWithTime(
            $matchingSearch,
            DataTable::SEARCH_AND,
            '2015-01-01'
        )));
        $this->assertSame([], $getResultIds($table->searchWithTime(
            $matchingSearch,
            DataTable::SEARCH_AND,
            '2020-01-01'
        )));

        $this->assertSame([$thirdId], $getResultIds($table->searchWithTime([
            ['column' => self::STRING_COLUMN, 'condition' => DataTable::COND_EQUAL_TO, 'value' => 'matching'],
            ['column' => self::STRING_COLUMN, 'condition' => DataTable::COND_EQUAL_TO, 'value' => 'other'],
        ], DataTable::SEARCH_OR, '2020-01-01')));
    }

    /**
     * @throws InvalidRowUpdateTime
     * @throws InvalidRowForUpdate
     * @throws RowDoesNotExist
     * @throws InvalidTimeStringException
     * @throws RowAlreadyExists
     */
    #[Test]
    public function testUpdateRowWithTime(): void
    {
        $table = $this->getTestUnitemporalDataTable();
        $idColumn = $table->getIdColumnName();
        $rowId = $table->createRowWithTime([self::INT_COLUMN => 1], '2010-01-01');

        $table->updateRowWithTime([$idColumn => $rowId, self::INT_COLUMN => 2], '2015-01-01');
        $this->assertSame(2, $table->getRowWithTime($rowId, '2015-01-01')[self::INT_COLUMN]);

        try {
            $table->updateRowWithTime([$idColumn => $rowId, self::INT_COLUMN => 3], '2014-01-01');
            $this->fail('An update before the latest version must be rejected.');
        } catch (InvalidRowUpdateTime) {
            $this->assertSame(UnitemporalDataTable::ERROR_INVALID_ROW_UPDATE_TIME, $table->getErrorCode());
        }

        try {
            $table->updateRowWithTime([self::INT_COLUMN => 3], '2016-01-01');
            $this->fail('An update without an ID must be rejected.');
        } catch (InvalidRowForUpdate) {
            $this->assertSame(DataTable::ERROR_ID_NOT_SET, $table->getErrorCode());
        }
    }

    /**
     * @throws InvalidRowUpdateTime
     * @throws InvalidTimeStringException
     * @throws RowAlreadyExists
     */
    #[Test]
    public function testDeleteRowWithTime(): void
    {
        $table = $this->getTestUnitemporalDataTable();
        $rowId = $table->createRowWithTime([self::STRING_COLUMN => 'value'], '2010-01-01');

        $this->assertSame(1, $table->deleteRowWithTime($rowId, '2015-01-01'));
        $this->assertTrue($table->rowExistsWithTime($rowId, '2014-12-31'));
        $this->assertFalse($table->rowExistsWithTime($rowId, '2015-01-01'));
        $this->assertSame(0, $table->deleteRowWithTime($rowId + 1, '2015-01-01'));

        // PdoUnitemporalDataTable, the de facto reference implementation,
        // permits a backdated deletion and stores the resulting invalid range.
        $backdatedId = $table->createRowWithTime([self::STRING_COLUMN => 'backdated'], '2010-01-01');
        $this->assertSame(1, $table->deleteRowWithTime($backdatedId, '2009-01-01'));
        $this->assertFalse($table->rowExistsWithTime($backdatedId, '2009-01-01'));
    }

    /**
     * @throws InvalidRowUpdateTime
     * @throws InvalidRowForUpdate
     * @throws InvalidTimeStringException
     * @throws RowAlreadyExists
     * @throws RowDoesNotExist
     */
    #[Test]
    public function testRowHistory(): void
    {
        $table = $this->getTestUnitemporalDataTable();
        $idColumn = $table->getIdColumnName();
        $rowId = $table->createRowWithTime([self::INT_COLUMN => 1], '2010-01-01');
        $table->updateRowWithTime([$idColumn => $rowId, self::INT_COLUMN => 2], '2015-01-01');

        $history = $table->getRowHistory($rowId);
        $this->assertCount(2, $history);
        $this->assertSame(1, $history[0][self::INT_COLUMN]);
        $this->assertSame(2, $history[1][self::INT_COLUMN]);
        $this->assertSame('2010-01-01 00:00:00.000000', $history[0][UnitemporalDataTable::DEFAULT_VALID_FROM_COLUMN]);
        $this->assertSame(TimeString::END_OF_TIMES, $history[1][UnitemporalDataTable::DEFAULT_VALID_UNTIL_COLUMN]);

        try {
            $table->getRowHistory($rowId + 1);
            $this->fail('History for a never-existing row must be rejected.');
        } catch (RowDoesNotExist) {
            $this->assertSame(DataTable::ERROR_ROW_DOES_NOT_EXIST, $table->getErrorCode());
        }
    }

    /**
     * @throws RowAlreadyExists|InvalidTimeStringException
     */
    #[Test]
    public function testInvalidTimes(): void
    {
        $table = $this->getTestUnitemporalDataTable();
        $rowId = $table->createRowWithTime([self::INT_COLUMN => 1], '2010-01-01');

        foreach ([
            fn() => $table->rowExistsWithTime($rowId, 'BadTime'),
            fn() => $table->getRowWithTime($rowId, 'BadTime'),
            fn() => $table->findRowsWithTime([self::INT_COLUMN => 1], 0, 'BadTime'),
            fn() => $table->updateRowWithTime([$table->getIdColumnName() => $rowId], 'BadTime'),
            fn() => $table->deleteRowWithTime($rowId, 'BadTime'),
        ] as $operation) {
            try {
                $operation();
                $this->fail('An invalid time must be rejected.');
            } catch (InvalidTimeStringException) {
                $this->assertSame(UnitemporalDataTable::ERROR_INVALID_TIME, $table->getErrorCode());
            }
        }
    }



    /**
     * @throws InvalidTimeStringException
     */
    #[Test]
    public function testGetAllRowsWithTime(): void
    {
        $dataTable = $this->getTestUnitemporalDataTable();
        $idColumn = $dataTable->getIdColumnName();

        $getValuesAtTime = function (string $time) use ($dataTable, $idColumn): array {
            $values = [];
            foreach ($dataTable->getAllRowsWithTime($time) as $row) {
                $values[$row[$idColumn]] = $row[self::STRING_COLUMN];
            }
            return $values;
        };

        $assertValuesAtTime = function (string $time, array $expected) use ($getValuesAtTime): void {
            $actual = $getValuesAtTime($time);
            $this->assertCount(count($expected), $actual);
            foreach ($expected as $rowId => $value) {
                $this->assertArrayHasKey($rowId, $actual);
                $this->assertSame($value, $actual[$rowId]);
            }
        };

        $assertValuesAtTime('2009-12-31', []);

        $firstId = $dataTable->createRowWithTime([self::STRING_COLUMN => 'first'], '2010-01-01');
        $secondId = $dataTable->createRowWithTime([self::STRING_COLUMN => 'second'], '2010-01-01');
        $thirdId = $dataTable->createRowWithTime([self::STRING_COLUMN => 'third'], '2010-01-01');
        $fourthId = $dataTable->createRowWithTime([self::STRING_COLUMN => 'fourth'], '2012-01-01');

        $assertValuesAtTime('2010-01-01', [
            $firstId => 'first',
            $secondId => 'second',
            $thirdId => 'third',
        ]);
        $assertValuesAtTime('2011-12-31', [
            $firstId => 'first',
            $secondId => 'second',
            $thirdId => 'third',
        ]);
        $assertValuesAtTime('2012-01-01', [
            $firstId => 'first',
            $secondId => 'second',
            $thirdId => 'third',
            $fourthId => 'fourth',
        ]);

        $dataTable->updateRowWithTime([
            $idColumn => $firstId,
            self::STRING_COLUMN => 'first updated',
        ], '2015-01-01');
        $dataTable->updateRowWithTime([
            $idColumn => $secondId,
            self::STRING_COLUMN => 'second updated',
        ], '2015-01-01');

        $assertValuesAtTime('2014-12-31', [
            $firstId => 'first',
            $secondId => 'second',
            $thirdId => 'third',
            $fourthId => 'fourth',
        ]);
        $assertValuesAtTime('2015-01-01', [
            $firstId => 'first updated',
            $secondId => 'second updated',
            $thirdId => 'third',
            $fourthId => 'fourth',
        ]);

        $dataTable->deleteRowWithTime($thirdId, '2020-01-01');
        $dataTable->deleteRowWithTime($fourthId, '2025-01-01');

        $assertValuesAtTime('2019-12-31', [
            $firstId => 'first updated',
            $secondId => 'second updated',
            $thirdId => 'third',
            $fourthId => 'fourth',
        ]);
        $assertValuesAtTime('2020-01-01', [
            $firstId => 'first updated',
            $secondId => 'second updated',
            $fourthId => 'fourth',
        ]);
        $assertValuesAtTime('2024-12-31', [
            $firstId => 'first updated',
            $secondId => 'second updated',
            $fourthId => 'fourth',
        ]);
        $assertValuesAtTime('2025-01-01', [
            $firstId => 'first updated',
            $secondId => 'second updated',
        ]);
    }
}