<?php



namespace ThomasInstitut\DataTable;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ThomasInstitut\DataTable\PdoProvider\PdoProvider;
use ThomasInstitut\DataTable\ReferenceTests\PdoUnitemporalDataTableReferenceTestCase;


#[CoversClass(MySqlUnitemporalDataTable::class)]
class MySqlUnitemporalDataTableTest extends PdoUnitemporalDataTableReferenceTestCase
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

    protected function constructPdoUnitemporalDataTable(PDO $pdo): PdoUnitemporalDataTable
    {
        return new MySqlUnitemporalDataTable($pdo, $this->getTableName(), $this->getIdColumnName());
    }

    protected function constructPdoUnitemporalDataTableForTable(PDO|PdoProvider $pdoOrProvider, string $tableName): PdoUnitemporalDataTable
    {
        return new MySqlUnitemporalDataTable($pdoOrProvider, $tableName, $this->getIdColumnName());
    }

    public function getTestDataTable(bool $resetTable = true, bool $newSession = false): PdoUnitemporalDataTable
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
        return new MySqlUnitemporalDataTable($restrictedPdo, $this->getTableName(), $this->getIdColumnName());
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
        $intCol = self::INT_COLUMN;
        $stringCol = self::STRING_COLUMN;
        $otherStringCol = self::STRING_COLUMN_2;
        $tableName = $this->getTableName();
        $idCol = $this->getIdColumnName();
        $validFromCol = PdoUnitemporalDataTable::FIELD_VALID_FROM;
        $validUntilCol = PdoUnitemporalDataTable::FIELD_VALID_UNTIL;

        $tableSetupSQL =<<<EOD
            DROP TABLE IF EXISTS `$tableName`;
            CREATE TABLE IF NOT EXISTS `$tableName` (
              $idCol int(11) UNSIGNED NOT NULL,
              $validFromCol datetime(6) NOT NULL,
              $validUntilCol datetime(6) NOT NULL,
              $intCol int(11) DEFAULT NULL,
              $stringCol varchar(100) DEFAULT NULL,
              $otherStringCol varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            ALTER TABLE `$tableName` ADD PRIMARY KEY( `$idCol`, `$validFromCol`, `$validUntilCol`);
EOD;
        $pdo->query($tableSetupSQL);
    }
    
    protected function resetTestDbWithBadTables(PDO $pdo): void
    {

        $intCol = self::INT_COLUMN;
        $stringCol = self::STRING_COLUMN;
        $idCol =  $this->getIdColumnName();
        $validFromCol = PdoUnitemporalDataTable::FIELD_VALID_FROM;
        $validUntilCol = PdoUnitemporalDataTable::FIELD_VALID_UNTIL;

        $tableSetupSQL =<<<EOD
            DROP TABLE IF EXISTS `test_table_bad_1`;
            CREATE TABLE IF NOT EXISTS `test_table_bad_1` (
              $idCol varchar(100) NOT NULL,
              $intCol int(11) DEFAULT NULL,
              $stringCol varchar(100) DEFAULT NULL,
              PRIMARY KEY (`$idCol`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            DROP TABLE IF EXISTS `test_table_bad_2`;                
            CREATE TABLE IF NOT EXISTS `test_table_bad_2` (
              $intCol int(11) DEFAULT NULL,
              $stringCol varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            DROP TABLE IF EXISTS `test_table_bad_3`;   
            CREATE TABLE IF NOT EXISTS `test_table_bad_3` (
              $idCol int(11) UNSIGNED NOT NULL,
              $validFromCol int(11) NOT NULL,
              $validUntilCol datetime(6) NOT NULL,
              $intCol int(11) DEFAULT NULL,
              $stringCol varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            DROP TABLE IF EXISTS `test_table_bad_4`;   
            CREATE TABLE IF NOT EXISTS `test_table_bad_4` (
              $idCol int(11) UNSIGNED NOT NULL,
              $validFromCol datetime(6) NOT NULL,
              $validUntilCol int(11) NOT NULL,
              $intCol int(11) DEFAULT NULL,
              $stringCol varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;    
            DROP TABLE IF EXISTS `test_table_bad_5`;  
            CREATE TABLE IF NOT EXISTS `test_table_bad_5` (
              $intCol int(11) UNSIGNED NOT NULL,
              $validUntilCol datetime(6) NOT NULL,
              $intCol int(11) DEFAULT NULL,
              $stringCol varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            DROP TABLE IF EXISTS `test_table_bad_6`;  
            CREATE TABLE IF NOT EXISTS `test_table_bad_6` (
              $idCol int(11) UNSIGNED NOT NULL,
              $validFromCol datetime(6) NOT NULL,
              $intCol int(11) DEFAULT NULL,
              $stringCol varchar(100) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;  
EOD;
        $pdo->query($tableSetupSQL);
    }

    #[Test]
    public function testCustomValidTimeColumnNames(): void
    {
        $pdo = $this->getPdo();
        $tableName = 'dt_test_table_custom_time_columns';
        $idColumn = $this->getIdColumnName();
        $validFromColumn = 'created_at';
        $validUntilColumn = 'expired_at';

        $pdo->exec("DROP TABLE IF EXISTS `$tableName`");
        $pdo->exec("CREATE TABLE `$tableName` (
            `$idColumn` int(11) UNSIGNED NOT NULL,
            `$validFromColumn` datetime(6) NOT NULL,
            `$validUntilColumn` datetime(6) NOT NULL,
            `a_string` varchar(100) DEFAULT NULL,
            PRIMARY KEY (`$idColumn`, `$validFromColumn`, `$validUntilColumn`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1");

        $table = new MySqlUnitemporalDataTable(
            $pdo,
            $tableName,
            $idColumn,
            $validFromColumn,
            $validUntilColumn
        );
        $rowId = $table->createRowWithTime(['a_string' => 'custom'], '2010-01-01');
        $row = $table->getRowWithTime($rowId, '2010-01-02');

        $this->assertSame($validFromColumn, $table->getValidFromColumnName());
        $this->assertSame($validUntilColumn, $table->getValidUntilColumnName());
        $this->assertSame('2010-01-01 00:00:00.000000', $row[$validFromColumn]);
        $this->assertSame('9999-12-31 23:59:59.999999', $row[$validUntilColumn]);
    }

    public function getTestUnitemporalDataTable(bool $resetTable = true, bool $newSession = false): UnitemporalDataTable
    {
       return $this->getTestDataTable($resetTable, $newSession);
    }
}
