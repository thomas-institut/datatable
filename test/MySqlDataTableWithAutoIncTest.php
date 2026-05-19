<?php

namespace ThomasInstitut\DataTable;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MySqlDataTable::class)]
class MySqlDataTableWithAutoIncTest extends MySqlDataTableTest
{

    #[\Override]
    protected function constructPdoDataTable(PDO $pdo): PdoDataTable
    {
        return new MySqlDataTable($pdo, $this->getTableName(), true, $this->getIdColumnName());
    }

    #[\Override]
    public function resetTestDb(PDO $pdo, bool $autoInc = false): void
    {
        parent::resetTestDb($pdo, true);
    }
}
