<?php

namespace ThomasInstitut\DataTable;

use PHPUnit\Framework\Attributes\Test;

class InMemoryUnitemporalDataTableTest extends ReferenceTests\UnitemporalDataTableReferenceTestCase
{
    private static ?InMemoryUnitemporalDataTable $motherTable = null;
    private static ?array $theData = null;

    public function multipleDataAccessSessionsAvailable(): bool
    {
        return false;
    }

    public function getTestUnitemporalDataTable(bool $resetTable = true, bool $newSession = false): UnitemporalDataTable
    {
        if (!self::$motherTable instanceof \ThomasInstitut\DataTable\InMemoryUnitemporalDataTable) {
            self::$theData = [];
            self::$motherTable = new InMemoryUnitemporalDataTable(self::$theData);
            $dataTable = self::$motherTable;
        } else {
            $dataTable = new InMemoryUnitemporalDataTable(self::$theData);
        }

        if ($resetTable) {
            self::$theData = [];
        }
        return $dataTable;
    }

    #[Test]
    public function testCustomValidTimeColumnNames(): void
    {
        $data = [];
        $table = new InMemoryUnitemporalDataTable($data);
        $table->setValidFromColumnName('custom_valid_from');
        $table->setValidUntilColumnName('custom_valid_until');

        $rowId = $table->createRowWithTime([self::STRING_COLUMN => 'custom'], '2010-01-01');
        $row = $table->getRowWithTime($rowId, '2010-01-02');

        $this->assertSame('custom_valid_from', $table->getValidFromColumnName());
        $this->assertSame('custom_valid_until', $table->getValidUntilColumnName());
        $this->assertSame('2010-01-01 00:00:00.000000', $row['custom_valid_from']);
        $this->assertSame('9999-12-31 23:59:59.999999', $row['custom_valid_until']);
    }
}