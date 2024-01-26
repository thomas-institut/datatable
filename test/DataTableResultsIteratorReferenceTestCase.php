<?php


namespace ThomasInstitut\DataTable;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;



require '../vendor/autoload.php';


/**
 * Reference test cases for DataTableResultsIterator implementations
 *
 */
abstract class DataTableResultsIteratorReferenceTestCase extends TestCase
{


    const INT_COLUM = 'value';
    const NUM_ROWS = 10;

    static ?DataTable $dataTable = null;


    abstract public function createDataTable() : DataTable;
    /**
     * @throws RowAlreadyExists
     */
    private function getDataTable() : DataTable {

        if (self::$dataTable === null) {

            self::$dataTable = $this->createDataTable();

            for ($i = 0; $i < self::NUM_ROWS; $i++) {
                self::$dataTable->createRow([ self::INT_COLUM => $i]);
            }
        }
        return self::$dataTable;
    }


    /**
     * @throws RowAlreadyExists
     */
    function getNonEmptyIterator(): DataTableResultsIterator
    {
        return $this->getDataTable()->getAllRows();
    }

    /**
     * @throws RowAlreadyExists
     */
    function getEmptyIterator(): DataTableResultsIterator
    {
        return $this->getDataTable()->findRows([ self::INT_COLUM => self::NUM_ROWS + 20]);
    }


    /**
     * @throws RowAlreadyExists
     */
    public function testEmptyIterator() : void
    {
        $iterator = $this->getEmptyIterator();

        $count = $iterator->count();
        $this->assertEquals(0, $iterator->count());

        $this->assertNull($iterator->getFirst());
        $this->assertNull($iterator->current());

        $numIterations = 0;
        foreach ($iterator as $ignored) {
            $numIterations++;
        }
        $this->assertEquals(0, $numIterations);
    }

    /**
     * @throws RowAlreadyExists
     */
    public function testGetFirst() : void{

        $iterator = $this->getNonEmptyIterator();
        $this->assertNotEquals(0, $iterator->count());
        $this->assertNotNull($iterator->getFirst());
        $firstResult = $iterator->getFirst();
        $this->assertValidResultRow($firstResult);
    }

    /**
     * @throws RowAlreadyExists
     */
    public function testForEachLoop() : void {
        $iterator = $this->getNonEmptyIterator();
        $numIterations = 0;
        foreach($iterator as $index => $row) {
            $this->assertIsInt($index);
            $this->assertValidResultRow($row);
            $numIterations++;
        }
        $this->assertEquals($iterator->count(), $numIterations);
    }


    private function assertValidResultRow(mixed $row) : void {
        $this->assertTrue(is_array($row));
        $this->assertNotCount(0, array_keys($row));
        $this->assertIsInt($row['id']);
        $this->assertNotEquals(0, $row['id']);
    }

}