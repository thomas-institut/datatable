<?php

namespace ThomasInstitut\DataTable;

use PDO;

require_once 'MySqlDataTableTest.php';

class MySqlDataTableWithAutoIncTest extends MySqlDataTableTest
{

    protected function constructMySqlDataTable(PDO $pdo) : MySqlDataTable {
        return new MySqlDataTable($pdo, self::TABLE_NAME, true, self::ID_COLUMN_NAME);
    }

    protected function getLoggerNamePrefix() : string {
        return 'MySqlDataTableAutoInc';
    }

    public function resetTestDb(PDO $pdo, bool $autoInc = false): void
    {
        parent::resetTestDb($pdo, true);
    }
}