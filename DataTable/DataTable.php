<?php

/*
 * The MIT License
 *
 * Copyright 2017-19 Rafael Nájera <rafael@najera.ca>.
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
namespace DataTable;

use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;

/**
 * An interface to a table made out of rows addressable by a unique key that
 * behaves mostly like a SQL table.
 *
 * It captures common functionality for this kind of table but does
 * not attempt to impose a particular implementation.
 * The idea is that one descendant of this class will implement the
 * table as an SQL table, but an implementation with arrays or
 * with something just as simple can be provided for testing.
 *
 * By default each row must have a unique int key: 'id'
 * The assignment of IDs is left to the class, not to the underlying
 * database.
 *
 * @author Rafael Nájera <rafael@najera.ca>
 */
abstract class DataTable implements LoggerAwareInterface, iErrorReporter
{
    
    const NULL_ROW_ID = -1;

    const SEARCH_AND = 0;
    const SEARCH_OR = 1;

    /**
     * Search condition types
     */

    const COND_EQUAL_TO = 0;
    const COND_NOT_EQUAL_TO = 1;
    const COND_LESS_THAN = 2;
    const COND_LESS_OR_EQUAL_TO = 3;
    const COND_GREATER_THAN = 4;
    const COND_GREATER_OR_EQUAL_TO = 5;

    /**
     * Error code constants
     */
    const ERROR_NO_ERROR = 0;
    const ERROR_UNKNOWN_ERROR = 1;
    const ERROR_ROW_DOES_NOT_EXIST = 102;
    const ERROR_ROW_ALREADY_EXISTS = 103;
    const ERROR_ID_NOT_INTEGER = 104;
    const ERROR_ID_NOT_SET = 105;
    const ERROR_ID_IS_ZERO = 106;
    const ERROR_EMPTY_RESULT_SET = 107;
    const ERROR_KEY_VALUE_NOT_FOUND = 108;
    const ERROR_INVALID_SEARCH_TYPE = 109;

    const ERROR_INVALID_SPEC_ARRAY = 110;
    const ERROR_SPEC_ARRAY_IS_EMPTY = 111;
    const ERROR_SPEC_INVALID_COLUMN = 112;
    const ERROR_SPEC_NO_VALUE = 113;
    const ERROR_SPEC_INVALID_CONDITION = 114;


    /** *********************************************************************
     * PUBLIC METHODS
     ************************************************************************/
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->setIdGenerator(new SequentialIdGenerator());
        $this->resetError();
        $this->setLogger(new NullLogger());
    }

    public function setIdGenerator(iIdGenerator $ig) : void {
        $this->idGenerator = $ig;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }


    /**
     *  Error reporting methods
     */

    /**
     * Returns a string describing the last error
     *
     * @return string
     */
    public function getErrorMessage() : string
    {
        return $this->errorMessage;
    }


    /**
     * @return int
     */
    public function getErrorCode() : int
    {
        return $this->errorCode;
    }

    /**
     * @param int $rowId
     * @return bool true if the row with the given Id exists
     */
    abstract public function rowExists(int $rowId) : bool;

    /**
     * Attempts to create a new row.
     *
     * If the given row does not have a value for 'id' or if the value
     * is equal to 0 a new id will be assigned.
     *
     * Otherwise, if the given Id is not an int or if the id
     * already exists in the table the function will throw
     * an exception
     *
     * @param array $theRow
     * @return int the Id of the newly created row
     * @throws RuntimeException
     */
    public function createRow(array $theRow) : int
    {
        $this->resetError();
        $preparedRow = $this->getRowWithGoodIdForCreation($theRow);
        return $this->realCreateRow($preparedRow);
    }

    /**
     * Gets the row with the given row Id.
     * If the row does not exist throws an InvalidArgument exception
     *
     * @param int $rowId
     * @return array The row
     * @throws InvalidArgumentException
     */
    abstract public function getRow(int $rowId) : array;

    /**
     * Gets all rows in the table
     *
     * @return array
     */
    abstract public function getAllRows() : array;


    /**
     * Deletes the row with the given Id.
     *
     * Returns the number of rows actually deleted without problems, which should be 1 if
     * the row the given Id existed in the datable, or 0 if there was no such row in
     * the first place.
     *
     * @param int $rowId
     * @return int
     */
    abstract public function deleteRow(int $rowId) : int;

    /**
     * Finds rows in the data table that match the values in $rowToMatch
     *
     * A row in the data table matches $rowToMatch if for every field
     * in $rowToMatch the row has exactly that same value.
     *
     * if $maxResults > 0, an array of max $maxResults will be returned
     * if $maxResults <= 0, all results will be returned
     *
     * @param array $rowToMatch
     * @param int $maxResults
     * @return array
     */
    public function findRows(array $rowToMatch, int $maxResults = 0) : array {
        $searchSpec = [];

        $givenRowKeys = array_keys($rowToMatch);
        foreach ($givenRowKeys as $key) {
            $searchSpec[] = [
                'column' => $key,
                'condition' => self::COND_EQUAL_TO,
                'value' => $rowToMatch[$key]
            ];
        }
        return $this->search($searchSpec, self::SEARCH_AND, $maxResults);
    }


    /**
     * Searches the datatable according to the given $searchSpec
     *
     * $searchSpec is an array of conditions.
     *
     * If $searchType is SEARCH_AND, the row must satisfy:
     *      $searchSpec[0] && $searchSpec[1] && ...  && $searchSpec[n]
     *
     * if  $searchType is SEARCH_OR, the row must satisfy the negation of the spec:
     *
     *      $searchSpec[0] || $searchSpec[1] || ...  || $searchSpec[n]
     *
     *
     * A condition is an array of the form:
     *
     *  $condition = [
     *      'column' => 'columnName',
     *      'condition' => one of (EQUAL_TO, NOT_EQUAL_TO, LESS_THAN, LESS_OR_EQUAL_TO, GREATER_THAN, GREATER_OR_EQUAL_TO)
     *      'value' => someValue
     * ]
     *
     * Notice that each condition type has a negation:
     *      EQUAL_TO  <==> NOT_EQUAL_TO
     *      LESS_THAN  <==>  GREATER_OR_EQUAL_TO
     *      LESS_OR_EQUAL_TO <==> GREATER_THAN
     *
     * if $maxResults > 0, an array of max $maxResults will be returned
     * if $maxResults <= 0, all results will be returned
     *
     * @param array $searchSpec
     * @param int $searchType
     * @param int $maxResults
     * @return array
     */
    abstract public function search(array $searchSpec, int $searchType = self::SEARCH_AND, int $maxResults = 0) : array;

    /**
     * Updates the table with the given row, which must contain an 'id'
     * field specifying the row to update.
     *
     * If the given row does not contain a valid 'id' field, or if the Id
     * is valid but there is no row with that id the table, an InvalidArgument exception
     * will be thrown.
     *
     * Only the keys given in $theRow are updated. The user must make sure
     * that not updating the non-given keys does not cause any problem
     *  (e.g., if in an SQL implementation the underlying SQL table does not
     *  have default values for the non-given keys)
     *
     * If the row was not successfully updated, throws a Runtime exception
     *
     * @param array $theRow
     * @return void
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function updateRow(array $theRow) : void
    {
        $this->resetError();
        if (!$this->isRowIdGoodForRowUpdate($theRow, 'DataTable updateRow')) {
            throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
        }
        $this->realUpdateRow($theRow);
    }
    


    /**
     * Returns the id of one row in which $row[$key] === $value
     * or false if such a row cannot be found or an error occurred whilst
     * trying to find it.
     *
     * @param string $key
     * @param mixed $value
     * @return int
     */
    abstract public function getIdForKeyValue(string $key, $value) : int;

    /**
     * Returns the max value in the given column.
     *
     * The actual column must exist and be numeric for the actual value returned
     * to be meaningful. Implementations may choose to throw a RunTime exception
     * in this case.
     *
     * @param string $columnName
     * @return int
     */
    abstract public function getMaxValueInColumn(string $columnName) : int;


    /**
     * @return int the max id in the table
     */
    abstract public function getMaxId() : int;


    /** *********************************************************************
     * ABSTRACT PROTECTED METHODS
     ************************************************************************/



    /**
     * Creates a row in the table, returns the id of the newly created
     * row.
     *
     * @param array $theRow
     * @return int
     */
    abstract protected function realCreateRow(array $theRow) : int;

    /**
     * Updates the given row, which must have a valid Id.
     * If there's not row with that id, it throw an InvalidArgument exception.
     *
     * Must throw a Runtime Exception if the row was not updated
     *
     * @param array $theRow
     * @return void
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    abstract protected function realUpdateRow(array $theRow) : void;


    /**
     *
     * PROTECTED METHODS
     *
     */

    /**
     * Returns theRow with a valid Id for creation: if there's no id
     * in the given row, the given Id is 0 or not an integer, the id is set to
     * an unused Id
     *
     * @param array $theRow
     * @throws RuntimeException
     * @throws InvalidArgumentException  if the given row has an invalid 'id' field
     * @return array
     */
    protected function getRowWithGoodIdForCreation($theRow) : array
    {
        if (!isset($theRow['id']) || !is_int($theRow['id']) || $theRow['id']===0) {
            $theRow['id'] = $this->getOneUnusedId();
        } else {
            if ($this->rowExists($theRow['id'])) {
                $this->setError('The row with given id ('. $theRow['id'] . ') already exists, cannot create',
                    self::ERROR_ROW_ALREADY_EXISTS);
                throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
            }
        }
        return $theRow;
    }
    
     /**
      * Returns a unique Id that does not exist in the table,
      * defaults to a sequential id if the idGenerator cannot
      * come up with one
      *
     * @return int
     *
     */
    protected function getOneUnusedId() : int
    {
        try{
            $unusedId = $this->idGenerator->getOneUnusedId($this);
        } catch (Exception $e) {
            $this->logWarning('Id generator error: ' . $e->getMessage() .
                ', defaulting to SequentialIdGenerator', $e->getCode());
            $unusedId = (new SequentialIdGenerator())->getOneUnusedId($this);
        }
        return $unusedId;
    }

    /**
     * Checks the validity of a specArray and returns an array of problems.
     *
     * Each problem is an array containing three fields:
     *  'specIndex' : the index of the element in the specArray that exhibits the problem
     *  'msg' :  a string describing the problem
     *  'code' : an error code associated with the problem
     *
     * @param array $specArray
     * @return array
     */
    protected function checkSearchSpecArrayValidity(array $specArray) : array  {

        $problems = [];

        if (count($specArray) === 0) {
            $problems[] = ['specIndex' => -1, 'msg' => 'specArray is empty', 'code' => self::ERROR_SPEC_ARRAY_IS_EMPTY];
            return $problems;
        }

        for($i=0; $i < count($specArray); $i++) {
            $spec = $specArray[$i];
            if (!isset($spec['column']) || !is_string($spec['column'])) {
                $problems[] = [
                    'specIndex' => $i,
                    'msg' => 'Invalid search condition, column field not found or not string',
                    'code' => self::ERROR_SPEC_INVALID_COLUMN
                ];
            }

            if (!isset($spec['value'])) {
                $problems[] = [
                    'specIndex' => $i,
                    'msg' =>'Invalid search condition, value to match not found' ,
                    'code' => self::ERROR_SPEC_NO_VALUE
                ];
            }

            if (!isset($spec['condition']) || !is_int($spec['condition'])) {
                $problems[] = [
                    'specIndex' => $i,
                    'msg' =>'Invalid search condition, no actual condition found' ,
                    'code' => self::ERROR_SPEC_INVALID_CONDITION
                ];
            } else {
                switch ($spec['condition']) {
                    case self::COND_EQUAL_TO:
                    case self::COND_NOT_EQUAL_TO:
                    case self::COND_LESS_THAN:
                    case self::COND_LESS_OR_EQUAL_TO:
                    case self::COND_GREATER_THAN:
                    case self::COND_GREATER_OR_EQUAL_TO:
                        break;

                    default:
                        $problems[] = [
                            'specIndex' => $i,
                            'msg' => 'Invalid condition type : ' . $spec['condition'],
                            'code' => self::ERROR_SPEC_INVALID_CONDITION
                            ];
                }
            }
        }
        return $problems;
    }

    /**
     * @param string $msg
     * @param int $code
     * @param array $otherContext
     */
    protected function setError(string $msg, int $code, array $otherContext = []): void
    {
        $this->setErrorMessage($msg);
        $this->setErrorCode($code);
        $this->log(LogLevel::ERROR, $msg, $code, $otherContext);
    }

    protected function resetError(): void
    {
        $this->setErrorCode(0);
        $this->setErrorMessage('');
    }

    protected function log(string $logLevel, string $msg, int $code, array $otherContext) {
        $this->logger->log($logLevel, $msg, array_merge([ 'code' => $code], $otherContext));
    }

    protected function logWarning(string $msg, int $code, array $otherContext = []) {
        $this->log(LogLevel::WARNING, $msg, $code, $otherContext);
    }

    /**********************************************************************
     * PRIVATE AREA
     ************************************************************************/

    /**
     * @var iIdGenerator
     */
    private $idGenerator;


    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     *
     * @var string
     */
    private $errorMessage;

    /**
     *
     * @var int
     */
    private $errorCode;

    /**
     * @param string $message
     */
    private function setErrorMessage(string $message) : void
    {
        $this->errorMessage = $message;
    }

    /**
     * @param int $code
     */
    private function setErrorCode(int $code) : void
    {
        $this->errorCode = $code;
    }


    /**
     * Checks that the given row has an id field that is a positive integer
     * If not, sets an error and returns false;
     *
     * @param $theRow
     * @param string $context
     * @return bool
     */
    protected function isRowIdGoodForRowUpdate($theRow, string $context) : bool {
        if (!isset($theRow['id']))  {
            $this->setError('Id not set in given row' . " ($context)", self::ERROR_ID_NOT_SET);
            return false;
        }

        if ($theRow['id'] <=0) {
            $this->setError('Id is equal to zero in given row' . " ($context)", self::ERROR_ID_IS_ZERO);
            return false;
        }
        if (!is_int($theRow['id'])) {
            $this->setError('Id in given row is not an integer' . " ($context)", self::ERROR_ID_NOT_INTEGER);
            return false;
        }
        return true;
    }

}
