<?php

namespace ThomasInstitut\DataTable;

use PDO;

require '../vendor/autoload.php';
require_once 'config.php';
require_once 'DataTableResultsIteratorReferenceTestCase.php';

class DataTestCaseTableResultsPdoIteratorTest extends DataTableResultsIteratorReferenceTestCase
{

    const TEST_TABLE_NAME = 'iterator_dt';
    public function createDataTable() : DataTable {
        $pdo = $this->getPdo();
        $this->setupDatabase($pdo);
        return new MySqlDataTable($pdo, self::TEST_TABLE_NAME, true);
    }


    protected function getPdo() : PDO
    {
        global $config;

        $dsn = 'mysql:dbname=' . $config['db'] . ';host=' . $config['host'];
        return new PDO($dsn,$config['user'],$config['pwd']);
    }

    private function setupDatabase(PDO $pdo) : void {
        $testTableName = self::TEST_TABLE_NAME;
        $autoIncrement = 'AUTO_INCREMENT';
        $idCol = 'id';
        $intCol = self::INT_COLUM;

        $tableSetupSQL =<<<EOD
            DROP TABLE IF EXISTS `$testTableName`;
            CREATE TABLE IF NOT EXISTS `$testTableName` (
              `$idCol` int(11) UNSIGNED NOT NULL $autoIncrement,
              `$intCol` int(11) DEFAULT NULL,
              PRIMARY KEY (`$idCol`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
EOD;
        $pdo->query($tableSetupSQL);
    }
}