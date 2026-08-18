<?php
declare(strict_types=1);
namespace ThomasInstitut\DataTable;

use Override;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MySqlDataTable::class)]
final class MySqlDataTableWithAutoIncTest extends MySqlDataTableTest
{

    #[Override]
    protected function constructPdoDataTable(PDO $pdo): MySqlDataTable
    {
        return new MySqlDataTable($pdo, $this->getTableName(), true, $this->getIdColumnName());
    }

    #[Override]
    public function resetTestDb(PDO $pdo, bool $autoInc = false): void
    {
        parent::resetTestDb($pdo, true);
    }
}
