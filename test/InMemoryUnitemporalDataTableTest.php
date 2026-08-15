<?php

namespace ThomasInstitut\DataTable;

class InMemoryUnitemporalDataTableTest extends ReferenceTests\UnitemporalDataTableReferenceTestCase
{

    public function multipleDataAccessSessionsAvailable(): bool
    {
        return false;
    }

    public function getTestUnitemporalDataTable(bool $resetTable = true, bool $newSession = false): UnitemporalDataTable
    {
        return new InMemoryUnitemporalDataTable();
    }
}