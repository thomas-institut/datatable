<?php
namespace ThomasInstitut\DataTable;
require '../vendor/autoload.php';


use PHPUnit\Framework\TestCase;

class FilterIteratorTest extends TestCase
{

   public function testFilter()
   {

       $isEven = function (int $num) : bool {
           return $num % 2 === 0;
       };


       $testArray = [ 1, 2, 3, 4, 5, 6];

       $iterator = new ArrayDataTableResultsIterator($testArray);


       $filterIterator = new FilterIterator($iterator, $isEven);

       $arrayFromFilter = iterator_to_array($filterIterator);
       $this->assertCount(3, $arrayFromFilter);
       $this->assertEquals([2, 4, 6], $arrayFromFilter);


       $filterIterator->rewind();
       $this->assertEquals(2, $filterIterator->current());
       $this->assertEquals(0, $filterIterator->key());

       foreach ($filterIterator as $num) {
           $this->assertEquals(0, $num % 2);
       }


   }

}