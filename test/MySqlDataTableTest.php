<?php

/*
 * The MIT License
 *
 * Copyright 2017 Rafael Nájera <rafael@najera.ca>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace ThomasInstitut\DataTable;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use ThomasInstitut\DataTable\PdoProvider\PdoProvider;
use ThomasInstitut\DataTable\ReferenceTests\PdoDataTableReferenceTestCase;
use ThomasInstitut\DataTable\SqlDialect\MySqlDialect;


#[CoversClass(MySqlDataTable::class)]
#[CoversClass(MySqlDialect::class)]
class MySqlDataTableTest extends PdoDataTableReferenceTestCase
{

    public int $numRows = 100;

    const string DB = 'dt';

    static private ?PDO $motherSession = null;

    static private int $pdoCount = 0;


    public static function setUpBeforeClass(): void
    {
        // Use a temporary PDO connection with root privileges to create the user
        $db = self::DB;
        $dsn = "mysql:dbname=$db;host=mysql";
        $pdo = new PDO($dsn, 'root', 'root');
        $createUserSQL = "CREATE USER IF NOT EXISTS 'restricted'@'%' IDENTIFIED BY 'restricted';";
        $grantPrivilegesSQL = "GRANT SELECT ON `$db`.* TO 'restricted'@'%';";
        $pdo->exec($createUserSQL);
        $pdo->exec($grantPrivilegesSQL);
    }

    public function multipleDataAccessSessionsAvailable(): bool
    {
        return true;
    }

    protected function getTableName(): string
    {
        return 'dt_test_table';
    }

    protected function getBadTableName1(): string
    {
        return 'bad_table_1';
    }

    protected function getBadTableName2(): string
    {
        return 'bad_table_2';
    }

    protected function getIdColumnName(): string
    {
        return 'row_id';
    }

    protected function constructPdoDataTable(PDO $pdo): PdoDataTable
    {
        return new MySqlDataTable($pdo, $this->getTableName(), false, $this->getIdColumnName());
    }

    protected function constructPdoDataTableWithProvider(PdoProvider $provider): PdoDataTable
    {
        return new MySqlDataTable($provider, $this->getTableName(), false, $this->getIdColumnName());
    }

    protected function constructPdoDataTableForTable(PDO|PdoProvider $pdoOrProvider, string $tableName): PdoDataTable
    {
        return new MySqlDataTable($pdoOrProvider, $tableName, false, $this->getIdColumnName());
    }

    protected function getMockColumnInfoResponse(): array
    {
        return ['Type' => 'int'];
    }

    public function getTestDataTable(bool $resetTable = true, bool $newSession = false): PdoDataTable
    {
        if (self::$motherSession === null) {
            self::$motherSession = $this->getPdo();
            $pdo = self::$motherSession;
            self::$pdoCount = 1;
        } else {
            if ($newSession) {
                $pdo = $this->getPdo();
                self::$pdoCount++;
            } else {
                $pdo = self::$motherSession;
            }
        }

        if ($resetTable) {
            $this->resetTestDb(self::$motherSession);
        }

        return $this->constructPdoDataTable($pdo);
    }

    protected function getRestrictedDt(): PdoDataTable
    {
        $restrictedPdo = $this->getRestrictedPdo();
        return new MySqlDataTable($restrictedPdo, $this->getTableName(), false, $this->getIdColumnName());
    }

    protected function getPdo(): PDO
    {
        $db = self::DB;
        $dsn = "mysql:dbname=$db;host=mysql";
        return new PDO($dsn, 'root', 'root');
    }

    protected function getRestrictedPdo(): PDO
    {
        $db = self::DB;
        $dsn = "mysql:dbname=$db;host=mysql";
        return new PDO($dsn, 'restricted', 'restricted');
    }

    protected function resetTestDb(PDO $pdo, bool $autoInc = false): void
    {
        $idCol = $this->getIdColumnName();
        $intCol = self::INT_COLUMN;
        $stringCol = self::STRING_COLUMN;
        $otherStringCol = self::STRING_COLUMN_2;
        $testTableName = $this->getTableName();

        $autoIncrement = $autoInc ? 'AUTO_INCREMENT' : '';

        $tableSetupSQL = <<<EOD
            DROP TABLE IF EXISTS `$testTableName`;
            CREATE TABLE IF NOT EXISTS `$testTableName` (
              $idCol int(11) UNSIGNED NOT NULL $autoIncrement,
              $intCol int(11) DEFAULT NULL,
              $stringCol varchar(100) DEFAULT NULL,
              $otherStringCol varchar(100) DEFAULT NULL,
              PRIMARY KEY (`$idCol`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
EOD;
        $pdo->query($tableSetupSQL);
    }

    protected function resetTestDbWithBadTables(PDO $pdo): void
    {
        $idCol = $this->getIdColumnName();
        $intCol = self::INT_COLUMN;
        $stringCol = self::STRING_COLUMN;

        $badTableName1 = $this->getBadTableName1();
        $badTableName2 = $this->getBadTableName2();


        $tableSetupSQL = <<<EOD
            DROP TABLE IF EXISTS `$badTableName1`;
            CREATE TABLE IF NOT EXISTS `$badTableName1` (
              $idCol varchar(100) NOT NULL,
              $intCol int(11) DEFAULT NULL,
              $stringCol varchar(100) DEFAULT NULL,
              PRIMARY KEY ($idCol)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            DROP TABLE IF EXISTS `$badTableName2`;                
            CREATE TABLE IF NOT EXISTS `$badTableName2` (
              $intCol int(11) DEFAULT NULL,
              $stringCol varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
EOD;
        $pdo->query($tableSetupSQL);
    }
}
