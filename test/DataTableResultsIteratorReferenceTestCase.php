<?php


namespace ThomasInstitut\DataTable;

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
        $this->assertValidResultRow($firstResult, __FUNCTION__);
    }

    /**
     * @throws RowAlreadyExists
     */
    public function testForEachLoop() : void {
        $iterator = $this->getNonEmptyIterator();
        $numIterations = 0;
        foreach($iterator as $index => $row) {
            $this->assertIsInt($index);
            $this->assertValidResultRow($row, __FUNCTION__);
            $numIterations++;
        }
        $this->assertEquals($iterator->count(), $numIterations);
    }


    private function assertValidResultRow(mixed $row, string $context) : void {
        $context .= ": testing row validity";
        $this->assertTrue(is_array($row), $context);
        $rowKeys = array_keys($row);
        $this->assertNotCount(0, $rowKeys, $context);
        // No numeric keys allowed
        foreach($rowKeys as $key) {
            $this->assertIsNotInt($key, $context . ": row keys must not be int");
        }
        $this->assertIsInt($row[DataTable::DEFAULT_ID_COLUMN_NAME], $context);
        $this->assertTrue(isset($row[self::INT_COLUM]), $context);
        $this->assertNotEquals(0, $row[DataTable::DEFAULT_ID_COLUMN_NAME], $context);
    }

}