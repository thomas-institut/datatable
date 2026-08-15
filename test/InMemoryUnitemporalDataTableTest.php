<?php

namespace ThomasInstitut\DataTable;

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
        if (self::$motherTable === null) {
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
}