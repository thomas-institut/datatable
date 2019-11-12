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
namespace DataTable;

use Exception;
use InvalidArgumentException;
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
abstract class DataTable implements iErrorReporter
{
    
    const NULL_ROW_ID = -1;

    /**
     * Error code constants
     */
    const ERROR_NO_ERROR = 0;
    const ERROR_UNKNOWN_ERROR = 1;
    const ERROR_CANNOT_GET_UNUSED_ID = 101;
    const ERROR_ROW_DOES_NOT_EXIST = 102;
    const ERROR_ROW_ALREADY_EXISTS = 103;
    const ERROR_ID_NOT_INTEGER = 104;
    const ERROR_ID_NOT_SET = 105;
    const ERROR_ID_IS_ZERO = 106;
    const ERROR_EMPTY_RESULT_SET = 107;
    const ERROR_KEY_VALUE_NOT_FOUND = 108;

    /** *********************************************************************
     * PUBLIC METHODS
     ************************************************************************/
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->setIdGenerator(new SequentialIdGenerator());
        $this->setErrorReporter(new SimpleErrorReporter());
    }

    public function setIdGenerator(iIdGenerator $ig) : void {
        $this->idGenerator = $ig;
    }

    public function setErrorReporter(iErrorReporter $er) : void {
        $this->errorReporter = $er;
    }

    /**
     *  Error Reporter methods
     */

    public function getErrorMessage() : string
    {
        return $this->errorReporter->getErrorMessage();
    }

    public function getErrorCode() : int
    {
        return $this->errorReporter->getErrorCode();
    }

    public function getWarnings() : array {
        return $this->errorReporter->getWarnings();
    }

    public function resetError() : void
    {
        $this->setError('', self::ERROR_NO_ERROR);
    }

    public function setErrorMessage(string $msg) : void
    {
        $this->errorReporter->setErrorMessage($msg);
    }

    public function setErrorCode(int $c) : void
    {
        $this->errorReporter->setErrorCode($c);
    }

    public  function setError(string $msg, int $code) : void {
        $this->errorReporter->setError($msg, $code);
    }

    public function addWarning(string $warning) : void {
        $this->errorReporter->addWarning($warning);
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
        $preparedRow = $this->prepareRowForRealCreation($theRow);
        return $this->realCreateRow($preparedRow);
    }


    /**
     * Searches the table for rows with the same data as the given row
     *
     * Only the keys given in $theRow are checked; so, for example,
     * if $theRow is missing a key that exists in the actual rows
     * in the table, those missing keys are ignored and the method
     * will return any row that matches exactly the given keys independently
     * of the missing ones.
     *
     * if $maxResults > 0, an array of max $maxResults will be returned
     * if $maxResults <= 0, all results will be returned
     *
     * @param array $theRow
     * @param int $numResults
     * @return array the results
     */
    abstract public function findRows(array $theRow, int $numResults = 0) : array;

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
        if (!isset($theRow['id']))  {
            $this->setErrorMessage('Id not set in given row, cannot update');
            $this->setErrorCode(self::ERROR_ID_NOT_SET);
            throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
        }
            
        if ($theRow['id']===0) {
            $this->setErrorMessage('Id is equal to zero in given row, cannot update');
            $this->setErrorCode(self::ERROR_ID_IS_ZERO);
            throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
        }
        if (!is_int($theRow['id'])) {
            $this->setErrorMessage('Id in given row is not an integer, cannot update');
            $this->setErrorCode(self::ERROR_ID_NOT_INTEGER);
            throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
        }
        $this->realUpdateRow($theRow);
    }
    
    /**
     * Gets all rows in the table 
     * 
     * @return array
     */
    abstract public function getAllRows() : array;

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
     * Returns the id of one row in which $row[$key] === $value
     * or false if such a row cannot be found or an error occurred whilst
     * trying to find it.
     *
     * @param string $key
     * @param mixed $value
     * @return int
     */
    abstract public function getIdForKeyValue(string $key, $value) : int;
    
   
    /** *********************************************************************
     * ABSTRACT PROTECTED METHODS
     ************************************************************************/
    

    
    /**
     * @return int the max id in the table
     */
    abstract public function getMaxId() : int;


    /**
     * Creates a row in the table, returns the id of the newly created
     * row.
     *
     * @param array $theRow
     * @return int
     */
    abstract protected function realCreateRow(array $theRow) : int;

    /**
     * Deletes the row with the given Id.
     * If there's no row with the given Id it must return false
     *
     * @param int $rowId
     * @return bool
     */
    abstract public function deleteRow(int $rowId) : bool;

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
     * Checks for errors in a row that is meant to be created in the table
     * and returns a version of the row with a new id if none
     * was given.
     *
     * @param array $theRow
     * @throws RuntimeException
     * @throws InvalidArgumentException  if the given row has an invalid 'id' field
     * @return array
     */
    protected function prepareRowForRealCreation($theRow) : array
    {
        if (!isset($theRow['id']) || $theRow['id']===0) {
            $theRow['id'] = $this->getOneUnusedId();
            if ($theRow['id'] === false) {
                if ($this->getErrorCode() === self::ERROR_NO_ERROR) {
                    // Give generic error information if a child class did not
                    // provide any
                    $msg = 'Could not generate a new id  when trying to create a new row';
                    $errorCode = self::ERROR_CANNOT_GET_UNUSED_ID;
                    $this->setError($msg, $errorCode);
                    throw new RuntimeException($msg, $errorCode);
                } else {
                    throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
                }
            }
        } else {
            if (!is_int($theRow['id'])) {
                $msg = 'Id field is present but is not a integer, cannot create row';
                $errorCode = self::ERROR_ID_NOT_INTEGER;
                $this->setError($msg, $errorCode);
                throw new InvalidArgumentException($msg, $errorCode);
            }
            if ($this->rowExists($theRow['id'])) {
                $msg = 'The row with given id ('. $theRow['id'] . ') already exists, cannot create';
                $errorCode = self::ERROR_ROW_ALREADY_EXISTS;
                $this->setError($msg, $errorCode);
                throw new RuntimeException($msg, $errorCode);
            }
        }
        return $theRow;
    }
    
     /**
      * Returns a unique Id that does not exist in the table
     * @return int
     *
     */
    protected function getOneUnusedId() : int
    {
        try{
            $unusedId = $this->idGenerator->getOneUnusedId($this);
        } catch (Exception $e) {
            $this->addWarning('Id generator error: ' . $e->getMessage() . ' code ' .
                $e->getCode() . ', defaulting to SequentialIdGenerator');
            $unusedId = (new SequentialIdGenerator())->getOneUnusedId($this);
        }
        return $unusedId;
    }


    /**********************************************************************
     * PRIVATE AREA
     ************************************************************************/

    /**
     * @var iIdGenerator
     */
    private $idGenerator;

    /**
     * @var iErrorReporter
     */
    private $errorReporter;

}
