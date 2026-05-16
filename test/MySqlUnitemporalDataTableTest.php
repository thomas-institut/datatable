<?php



namespace ThomasInstitut\DataTable;

use Exception;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use ThomasInstitut\TimeString\InvalidTimeZoneException;
use ThomasInstitut\TimeString\MalformedStringException;
use ThomasInstitut\TimeString\TimeString;

#[CoversClass(MySqlUnitemporalDataTable::class)]
class MySqlUnitemporalDataTableTest extends MySqlDataTableTest
{

    protected function constructMySqlDataTable(PDO $pdo) : MySqlDataTable {
        return new MySqlUnitemporalDataTable($pdo, self::TABLE_NAME, self::ID_COLUMN_NAME);
    }

    protected function getLoggerNamePrefix() : string {
        return 'MySqlUnitemporalDt';
    }

    public function resetTestDb(PDO $pdo, bool $autoInc = false) : void
    {
//        print "Resetting Unitemporal table...";
        $intCol = self::INT_COLUMN;
        $stringCol = self::STRING_COLUMN;
        $otherStringCol = self::STRING_COLUMN_2;
        $tableName = self::TABLE_NAME;
        $idCol = self::ID_COLUMN_NAME;
        $validFromCol = MySqlUnitemporalDataTable::FIELD_VALID_FROM;
        $validUntilCol = MySqlUnitemporalDataTable::FIELD_VALID_UNTIL;

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
//        print "done\n";

    }
    
    public function resetTestDbWithBadTables(PDO $pdo) : void
    {

        $intCol = self::INT_COLUMN;
        $stringCol = self::STRING_COLUMN;
        $idCol =  self::ID_COLUMN_NAME;
        $validFromCol = MySqlUnitemporalDataTable::FIELD_VALID_FROM;
        $validUntilCol = MySqlUnitemporalDataTable::FIELD_VALID_UNTIL;

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
    
      public function getRestrictedDt() : MySqlDataTable
    {
        $restrictedPdo = $this->getRestrictedPdo();
        return new MySqlUnitemporalDataTable($restrictedPdo, self::TABLE_NAME, self::ID_COLUMN_NAME);
    }
    
    #[Test]
    public function testBadTables(): void
    {

        $pdo = $this->getPdo();
        $this->resetTestDbWithBadTables($pdo);

        $exceptionCaught = false;
        try {
            new MySqlUnitemporalDataTable($pdo, 'test_table_bad_1', self::ID_COLUMN_NAME);
        } catch(RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);


        $exceptionCaught = false;
        try {
            new MySqlUnitemporalDataTable($pdo, 'test_table_bad_2', self::ID_COLUMN_NAME);
        } catch(RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            new MySqlUnitemporalDataTable($pdo, 'test_table_bad_3', self::ID_COLUMN_NAME);
        } catch(RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            new MySqlUnitemporalDataTable($pdo, 'test_table_bad_4', self::ID_COLUMN_NAME);
        } catch(RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            new MySqlUnitemporalDataTable($pdo, 'test_table_bad_5', self::ID_COLUMN_NAME);
        } catch(RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            new MySqlUnitemporalDataTable($pdo, 'test_table_bad_6', self::ID_COLUMN_NAME);
        } catch(RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $exceptionCaught = false;
        try {
            new MySqlUnitemporalDataTable($pdo, 'non_existent_table', self::ID_COLUMN_NAME);
        } catch(RuntimeException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
    }

    /**
     * @throws InvalidTimeStringException
     * @throws MalformedStringException
     * @throws RowDoesNotExist
     * @throws InvalidRowUpdateTime
     * @throws InvalidTimeZoneException
     * @throws RowAlreadyExists
     */
    #[Test]
    public function testFindRowsWithTime(): void
    {
        /** @var MySqlUnitemporalDataTable $dataTable */
        $dataTable = $this->getTestDataTable();
        
        $timeZero = TimeString::fromVariable('2010-01-01');
        $times = [ '2014-01-01',
            '2015-01-01',
            '2016-01-01'];
       
        $nEntries = 10;
        $someInt = 1000;
        $nTimes = count($times);
        
        // Create different versions of $nEntries
        $ids = [];
        for ($i = 0; $i < $nEntries; $i++) {
            $rowId = $dataTable->createRowWithTime(
                [self::INT_COLUMN => $someInt],
                $timeZero
            );
            $ids[] = $rowId;
            $timesCount = 1;
            foreach ($times as $t) {
                $t = TimeString::fromVariable($t);
                $dataTable->realUpdateRowWithTime([self::ID_COLUMN_NAME => $rowId,
                    self::STRING_COLUMN => 'Value' .
                    $timesCount++], $t);
            }
        }
        
        
        // Check latest versions
        foreach ($ids as $rowId) {
            $row = $dataTable->getRow($rowId);
            $this->assertNotNull($row);
            $this->assertEquals($someInt, $row[self::INT_COLUMN]);
            $this->assertEquals('Value' . $nTimes, $row[self::STRING_COLUMN]);
        }
        
        // Only the last versions should show up in these searches
        for($i = 1; $i < $nTimes; $i++) {
            $foundsRows = $dataTable->findRows([self::STRING_COLUMN => 'Value' . $i]);
            $this->assertEquals(0, $foundsRows->count());
        }

        $foundsRows = $dataTable->findRows([self::STRING_COLUMN => 'Value' . $nTimes]);
        $this->assertEquals($nEntries, $foundsRows->count());
        
        // Time info should be irrelevant for the search:
        $foundsRows3 = $dataTable->findRows(['valid_from'=> $timeZero,
            self::STRING_COLUMN => 'Value3']);
        $this->assertEquals($nEntries, $foundsRows3->count());
        
        $foundsRows3 = $dataTable->findRows(['valid_until'=> $timeZero,
            self::STRING_COLUMN => 'Value3']);
        $this->assertEquals($nEntries, $foundsRows3->count());
        
        $foundsRows3 = $dataTable->findRows(['valid_from'=> $timeZero,
            'valid_until' => $timeZero,
            self::STRING_COLUMN => 'Value3']);
        $this->assertEquals($nEntries, $foundsRows3->count());

        // Search the keys in the times they are valid
        $foundRows4 = $dataTable->findRowsWithTime(
            [self::STRING_COLUMN => 'Value3'],
            false,
            '2016-01-01 12:00:00'
        );
        $this->assertEquals(10, $foundRows4->count());
        
        // timestamps should be fine as well
        $foundRows4b = $dataTable->findRowsWithTime(
            [self::STRING_COLUMN => 'Value3'],
            false,
            // a day ago
            TimeString::fromVariable(time()-86400)
        );
        $this->assertEquals(10, $foundRows4b->count());
        
        $foundRows5 = $dataTable->findRowsWithTime(
            [self::STRING_COLUMN => 'Value2'],
            false,
            '2015-01-01 12:00:00'
        );
        $this->assertEquals(10, $foundRows5->count());
        
        $foundRows6 = $dataTable->findRowsWithTime([self::STRING_COLUMN => 'Value1'],
            false,
            '2014-01-01 12:00:00'
        );
        $this->assertEquals(10, $foundRows6->count());
        
        // Search the common key, only the latest version should
        // be returned
        $foundRows7 = $dataTable->findRows([self::INT_COLUMN => $someInt]);
        $this->assertEquals(10, $foundRows7->count());
        foreach ($foundRows7 as $row) {
            $this->assertEquals('Value3', $row[self::STRING_COLUMN]);
        }
        
        // Search the common key at other times
        $foundRows8 = $dataTable->findRowsWithTime(
            [self::INT_COLUMN => $someInt],
            false,
            '2015-01-01 12:00:00'
        );
        $this->assertEquals(10, $foundRows8->count());
        foreach ($foundRows8 as $row) {
            $this->assertEquals('Value2', $row[self::STRING_COLUMN]);
        }
        
        $foundRows9 = $dataTable->findRowsWithTime(
            [self::INT_COLUMN => $someInt],
            false,
            '2014-01-01 12:00:00'
        );
        $this->assertEquals(10, $foundRows9->count());
        foreach ($foundRows9 as $row) {
            $this->assertEquals('Value1', $row[self::STRING_COLUMN]);
        }
        
        $foundRows10 = $dataTable->findRowsWithTime(
            [self::INT_COLUMN => $someInt],
            false,
            '2013-01-01'
        );
        $this->assertEquals(10, $foundRows10->count());
        foreach ($foundRows10 as $row) {
            $this->assertTrue(is_null($row[self::STRING_COLUMN]));
        }
        
        $foundRows11 = $dataTable->findRowsWithTime(
            [self::INT_COLUMN => $someInt],
            false,
            '2000-01-01 12:00:00'
        );
        $this->assertEquals(0, $foundRows11->count());
    }

    /**
     * @throws RowAlreadyExists
     * @throws InvalidTimeStringException
     */
    #[Test]
    public function testCreateRowWithTime(): void
    {
        /** @var MySqlUnitemporalDataTable $dataTable */
        $dataTable = $this->getTestDataTable();
        $time = TimeString::now();


        // Bad time
        $exceptionCaught = false;
        try{
            $dataTable->createRowWithTime(
                [self::ID_COLUMN_NAME => 1, self::STRING_COLUMN_2 => 'test'],
                'BadTime');
        } catch (InvalidTimeStringException|RowAlreadyExists) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(UnitemporalDataTable::ERROR_INVALID_TIME, $dataTable->getErrorCode());

        $id1 = $dataTable->createRowWithTime(
            [self::ID_COLUMN_NAME => 1, self::STRING_COLUMN_2 => 'test'],
            $time
        );
        $this->assertEquals(1, $id1);

        // ID is not an integer: a new id must be generated

        $id2 = $dataTable->createRowWithTime([self::ID_COLUMN_NAME => 'NotaNumber',self::STRING_COLUMN_2 => 'test2'],$time);
        $this->assertNotEquals($id1, $id2);

        // Trying to create an existing row
        $exceptionCaught = false;
        try {
            $dataTable->createRowWithTime([self::ID_COLUMN_NAME => 1,
                self::STRING_COLUMN_2 => 'AnotherValue'], $time);
        } catch(RowAlreadyExists) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $row = $dataTable->getRow($id1);
        $this->assertNotNull($row);
        $this->assertEquals('test', $row[self::STRING_COLUMN_2]);
    }

    /**
     * @throws RowAlreadyExists
     * @throws InvalidTimeStringException
     */
    #[Test]
    public function testDeleteRowWithTime(): void
    {
        /** @var MySqlUnitemporalDataTable $dataTable */
        $dataTable = $this->getTestDataTable();
        
        $newId = $dataTable->createRow([self::STRING_COLUMN_2 => 'test']);
        $this->assertNotFalse($newId);

        // Bad time
        $exceptionCaught = false;
        try{
            $dataTable->deleteRowWithTime($newId, 'BadTime');
        } catch (InvalidTimeStringException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(UnitemporalDataTable::ERROR_INVALID_TIME, $dataTable->getErrorCode());


        $time = TimeString::now();
        
        $result = $dataTable->deleteRowWithTime($newId, $time);
        $this->assertEquals($newId, $result);

    }

    /**
     * @throws InvalidTimeStringException
     */
    #[Test]
    public function testGetAllRowsWithTime(): void
    {
        /**
         * @var MySqlUnitemporalDataTable $dataTable
         */
        $dataTable = $this->getTestDataTable();

        $this->assertEquals(0, iterator_count($dataTable->getAllRowsWithTime('2019-01-01')));
    }

    /**
     * @throws RowAlreadyExists
     * @throws InvalidTimeStringException
     * @throws InvalidRowUpdateTime
     * @throws RowDoesNotExist
     */
    #[Test]
    public function testBadTimes(): void
    {

        /**
         * @var MySqlUnitemporalDataTable $dataTable
         */
        $dataTable = $this->getTestDataTable();

        // get all rows
        $exceptionCaught = false;
        try {
            $dataTable->getAllRowsWithTime('BadTime');
        } catch (InvalidTimeStringException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(UnitemporalDataTable::ERROR_INVALID_TIME, $dataTable->getErrorCode());

        $newId = $dataTable->createRowWithTime([self::INT_COLUMN => 1000], '2010-10-10 10:10:10');

        $this->assertNotEquals(0, $newId);

        // Get row
        $exceptionCaught = false;
        $theRow = [];
        try {
            $theRow = $dataTable->getRowWithTime($newId, 'BadTime');
        } catch (InvalidTimeStringException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(UnitemporalDataTable::ERROR_INVALID_TIME, $dataTable->getErrorCode());
        $this->assertEquals([], $theRow);

        // update row
        $exceptionCaught = false;
        try {
            $dataTable->realUpdateRowWithTime([ self::ID_COLUMN_NAME => $newId, self::INT_COLUMN => 1001], 'BadTime');
        } catch (InvalidTimeStringException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(UnitemporalDataTable::ERROR_INVALID_TIME, $dataTable->getErrorCode());

        $theRow = $dataTable->getRow($newId);
        $this->assertNotNull($theRow);
        $this->assertEquals(1000, $theRow[self::INT_COLUMN]);


        // find Rows
        $foundRows = [];
        $exceptionCaught = false;
        try {
            $foundRows = $dataTable->findRowsWithTime([ self::INT_COLUMN => 1000], 0, 'BadTime');
        } catch (InvalidTimeStringException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(UnitemporalDataTable::ERROR_INVALID_TIME, $dataTable->getErrorCode());

        $this->assertEquals([], $foundRows);

    }


    /**
     * @throws RowAlreadyExists
     * @throws InvalidTimeStringException
     * @throws MalformedStringException
     * @throws InvalidTimeZoneException
     */
    #[Test]
    public function testRowExists(): void
    {
        /**
         * @var MySqlUnitemporalDataTable $dataTable
         */
        $dataTable = $this->getTestDataTable();

        $rowId = $dataTable->createRowWithTime([self::INT_COLUMN => 1000], TimeString::now());

        $this->assertTrue($dataTable->rowExistsWithTime($rowId,TimeString::now()));
        $this->assertFalse($dataTable->rowExistsWithTime($rowId + 1,TimeString::now()));



        $this->assertFalse($dataTable->rowExistsWithTime($rowId, TimeString::fromString('2010-10-10')));

    }

    /**
     * @throws InvalidSearchType
     * @throws InvalidSearchSpec
     */
    #[Test]
    public function testSearchWithTime(): void
    {
        /**
         * @var MySqlUnitemporalDataTable $dataTable
         */
        $dataTable = $this->getTestDataTable();
        // search is not implemented yet
        $this->assertEquals(0, $dataTable->searchWithTime([], DataTable::SEARCH_AND, TimeString::now())->count());
        $this->assertEquals(DataTable::ERROR_NOT_IMPLEMENTED, $dataTable->getErrorCode());
    }


    /**
     * @throws InvalidTimeStringException
     * @throws InvalidTimeZoneException
     * @throws RowDoesNotExist
     * @throws InvalidRowForUpdate
     * @throws RowAlreadyExists
     * @throws InvalidRowUpdateTime
     */
    #[Test]
    public function testUpdateRowWithTime(): void
    {
        /**
         * @var MySqlUnitemporalDataTable $dataTable
         */
        $dataTable = $this->getTestDataTable();

        $rowId = $dataTable->createRowWithTime([self::INT_COLUMN => 1000], TimeString::now());

        $theRow = $dataTable->getRow($rowId);
        $this->assertNotNull($theRow);

        $theRow[self::INT_COLUMN] = 1001;

        $dataTable->updateRowWithTime($theRow, TimeString::now());
        $theRow2 = $dataTable->getRow($rowId);
        $this->assertNotNull($theRow2);
        $this->assertEquals($theRow[self::INT_COLUMN], $theRow2[self::INT_COLUMN]);

        $exceptionCaught = false;
        try {
            $dataTable->updateRowWithTime([self::INT_COLUMN => 1002], TimeString::now());
        } catch (InvalidRowForUpdate){
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(DataTable::ERROR_ID_NOT_SET, $dataTable->getErrorCode());

        // Update with time before last update
        $theRow[self::INT_COLUMN] = 1002;
        $exceptionCaught = false;
        try {
            $dataTable->updateRowWithTime($theRow, TimeString::fromTimeStamp(time() - 600));
        } catch (InvalidRowUpdateTime){
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(UnitemporalDataTable::ERROR_INVALID_ROW_UPDATE_TIME, $dataTable->getErrorCode());

    }

    /**
     * @throws InvalidTimeStringException
     * @throws InvalidTimeZoneException
     * @throws MalformedStringException
     * @throws RowDoesNotExist
     * @throws InvalidRowForUpdate
     * @throws RowAlreadyExists
     * @throws InvalidRowUpdateTime
     */
    #[Test]
    public function testRowHistory(): void
    {
        /**
         * @var MySqlUnitemporalDataTable $dataTable
         */
        $dataTable = $this->getTestDataTable();


        $times = [
            '2010-01-01',
            '2014-01-01',
            '2015-01-01',
            '2016-01-01'];

        $initialIntValue = 1000;
        $rowId = $dataTable->createRowWithTime([ self::INT_COLUMN => 1000], TimeString::fromString($times[0]));
        for($i = 1; $i < count($times); $i++){
            $dataTable->updateRowWithTime(
                [ self::ID_COLUMN_NAME => $rowId, self::INT_COLUMN => $initialIntValue+$i ],
                TimeString::fromString($times[$i]));
        }

        $rowHistory = $dataTable->getRowHistory($rowId);
        $this->assertCount(4, $rowHistory);
        for($i=0; $i<count($rowHistory); $i++) {
            $this->assertEquals($rowId, $rowHistory[$i][self::ID_COLUMN_NAME]);
            $this->assertEquals($initialIntValue+$i, $rowHistory[$i][self::INT_COLUMN]);
            $this->assertEquals(TimeString::fromString($times[$i]),$rowHistory[$i][MySqlUnitemporalDataTable::FIELD_VALID_FROM]);
        }
        $this->assertEquals(TimeString::END_OF_TIMES,$rowHistory[count($rowHistory)-1][MySqlUnitemporalDataTable::FIELD_VALID_UNTIL]);

        $exceptionCaught = false;
        try {
            $dataTable->getRowHistory($rowId + 5);
        } catch (InvalidArgumentException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);
        $this->assertEquals(DataTable::ERROR_ROW_DOES_NOT_EXIST, $dataTable->getErrorCode());

    }

    /**
     * @throws InvalidTimeStringException
     * @throws InvalidArgumentException
     * @throws MalformedStringException
     * @throws InvalidTimeZoneException
     * @throws RowDoesNotExist
     * @throws InvalidRowForUpdate
     * @throws InvalidRowUpdateTime
     */
    #[Test]
    public function testConsistency(): void
    {
        /**
         * @var MySqlUnitemporalDataTable $dataTable
         */
        $dataTable = $this->getTestDataTable();
//        $initialIntValue = 0;
        $initialYear = 1971;
        $lastYear = 1975;
        $rowId = 1;
        for ($i = $initialYear; $i <= $lastYear; $i++) {
            $time = "$i-01-01";
            if ($i === $initialYear) {
                try {
                    $rowId = $dataTable->createRowWithTime([self::INT_COLUMN => $i], TimeString::fromString($time));
                } catch (Exception $e) {
                    print ("Exception: " . $e->getMessage());
                }
                //print ("Row ID is $rowId\n");
            } else {
                $dataTable->updateRowWithTime(
                    [self::ID_COLUMN_NAME => $rowId, self::INT_COLUMN => $i],
                    TimeString::fromString($time));
            }
        }
        $issues = $dataTable->checkConsistency();
        $this->assertCount(0, $issues);
    }
}
