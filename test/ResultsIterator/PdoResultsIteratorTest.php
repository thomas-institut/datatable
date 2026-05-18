<?php

namespace ThomasInstitut\DataTable\ResultsIterator;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use ThomasInstitut\DataTable\DataTable;
use ThomasInstitut\DataTable\MySqlDataTable;
use ThomasInstitut\DataTable\MySqlDataTableTest;
use ThomasInstitut\DataTable\ReferenceTests\ResultsIteratorReferenceTestCase;

#[CoversClass(PdoResultsIterator::class)]
class PdoResultsIteratorTest extends ResultsIteratorReferenceTestCase
{

    const bool AUTO_INC = false;
    const string TEST_TABLE_NAME = 'iterator_dt';
    public function createDataTable() : DataTable {
        $pdo = $this->getPdo();
        $this->setupDatabase($pdo);
        return new MySqlDataTable($pdo, self::TEST_TABLE_NAME, self::AUTO_INC);
    }


    protected function getPdo() : PDO
    {
        $db = MySqlDataTableTest::DB;
        $dsn = "mysql:dbname=$db;host=mysql";
        return new PDO($dsn, 'root', 'root');
    }

    private function setupDatabase(PDO $pdo) : void {
        $testTableName = self::TEST_TABLE_NAME;
        $autoIncrement = self::AUTO_INC ? 'AUTO_INCREMENT' : '';
        $idCol = DataTable::DEFAULT_ID_COLUMN_NAME;
        $intCol = self::INT_COLUM;

        $tableSetupSQL =<<<EOD
            DROP TABLE IF EXISTS `$testTableName`;
            CREATE TABLE IF NOT EXISTS `$testTableName` (
              $idCol int(11) UNSIGNED NOT NULL $autoIncrement,
              $intCol int(11) DEFAULT NULL,
              PRIMARY KEY (`$idCol`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
EOD;
        $pdo->query($tableSetupSQL);
    }
}