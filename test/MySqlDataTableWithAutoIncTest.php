<?php

namespace ThomasInstitut\DataTable;

require_once 'MySqlDataTableTest.php';

class MySqlDataTableWithAutoIncTest extends MySqlDataTableTest
{

    public function createEmptyDt() : GenericDataTable
    {
        $pdo = $this->getPdo();
        $this->resetTestDb($pdo, true);

        $dt = new MySqlDataTable($pdo, self::TABLE_NAME, true);
        $dt->setLogger($this->getLogger()->withName('MySqlDataTable (' . self::TABLE_NAME . ')'));
        return $dt;
    }
}