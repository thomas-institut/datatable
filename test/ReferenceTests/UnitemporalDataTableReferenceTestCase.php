<?php

namespace ThomasInstitut\DataTable\ReferenceTests;

use PHPUnit\Framework\Attributes\Test;
use ThomasInstitut\DataTable\DataTable;
use ThomasInstitut\DataTable\Exception\InvalidTimeStringException;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
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
        $idCol = DataTable::DEFAULT_ID_COLUMN_NAME;
        $validUntilCol = UnitemporalDataTable::DEFAULT_VALID_UNTIL_COLUMN;
        $validFromCol = UnitemporalDataTable::DEFAULT_VALID_FROM_COLUMN;

        $table->createRowWithTime([$colName => 'Jane'], TimeString::fromTimestamp($referenceTimestamp));
//        $table->createRowWithTime([$colName => 'George'], TimeString::fromTimestamp($referenceTimestamp+1));
//        $table->createRowWithTime([$colName => 'Mary'], TimeString::fromTimestamp($referenceTimestamp+1));
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
}