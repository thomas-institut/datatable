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
abstract class DataTable
{
    
    // ERROR CODE CONSTANTS
    const DATATABLE_NOERROR = 0;
    const DATATABLE_UNKNOWN_ERROR = 1;
    
    const DATATABLE_CANNOT_GET_UNUSED_ID = 10;
    const DATATABLE_ROW_DOES_NOT_EXIST = 20;
    const DATATABLE_ROW_ALREADY_EXISTS = 30;
    const DATATABLE_ID_NOT_INTEGER = 40;
    const DATATABLE_MAX_RESULTS_LESS_THAN_ONE = 50;
    const DATATABLE_ID_NOT_SET = 60;
    const DATATABLE_ID_IS_ZERO = 70;
    const DATATABLE_EMPTY_RESULT_SET = 80;
    const DATATABLE_KEY_VALUE_NOT_FOUND = 90;
    
    

    
    // PROTECTED MEMBERS
    
    /**
     *
     * @var string 
     */
    protected $errorMessage;
    
    /**
     *
     * @var int
     */
    protected $errorCode;
    
    //
    // PUBLIC METHODS
    //
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->resetError();
    }
    
    /**
     * Returns a string describing the last error
     * 
     * @return string
     */
    public function getErrorMessage() 
    {
        return $this->errorMessage;
    }
    
    /**
     * Returns an integer code number identifying the last error
     * 
     * @return int
     */
    public function getErrorCode() 
    {
        return $this->errorCode;
    }
    
    /**
     * @return bool true if the row with the given Id exists
     */
    abstract public function rowExistsById(int $rowId);
    
    /**
     * Attempts to create a new row.
     * If the given row does not have a value for 'id' or if the value
     * is equal to 0 a new id will be assigned.
     * Otherwise, if the given Id is not an int or if the id
     * already exists in the table the function will return
     * false (updateRow must be used in this latter case)
     *
     * @return int the Id of the new created row, or false if the row could
     *             not be created
     */
    public function createRow($theRow)
    {
        $this->resetError();
        if (!isset($theRow['id']) || $theRow['id']===0) {
            $theRow['id'] = $this->getOneUnusedId();
            if ($theRow['id'] === false) {
                if ($this->getErrorCode() === self::DATATABLE_NOERROR) {
                    // Give generic error information if a child class did not
                    // provide any
                    $this->setErrorMessage('Could not generate a new id when trying to create a new row');
                    $this->setErrorCode(self::DATATABLE_CANNOT_GET_UNUSED_ID);
                }
                return false;
            }
        } else {
            if (!is_int($theRow['id'])) {
                $this->setErrorMessage('Id field is present but is not a integer, cannot create row');
                $this->setErrorCode(self::DATATABLE_ID_NOT_INTEGER);
                return false;
            }
            if ($this->rowExistsById($theRow['id'])) {
                $this->setErrorMessage('The row with given id (' . $theRow['id'] . ') already exists, cannot create');
                $this->setErrorCode(self::DATATABLE_ROW_ALREADY_EXISTS);
                return false;
            }
        }
        return $this->realCreateRow($theRow);
    }
    
    /**
     * @return bool true if the row was deleted (or if the row did not
     *              exist in the first place;
     */
    public function deleteRow(int $rowId)
    {
        $this->resetError();
        if (!$this->rowExistsById($rowId)) {
            $this->setErrorMessage('The row with id ' . $rowId . ' does not exist, cannot delete');
            $this->setErrorCode(self::DATATABLE_ROW_DOES_NOT_EXIST);
            return true;
        }
        return $this->realDeleteRow($rowId);
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
     * if $maxResults === false, all results will be returned
     * if $maxResults > 0, an array of max $maxResults will be returned
     * if $maxResults <= 0, returns false
     *
     * @return int the Row Id
     */
    public function findRows($theRow, $maxResults = false)
    {
        $this->resetError();
        if ($maxResults !== false && $maxResults <= 0) {
            $this->setErrorMessage('Asked to limit number of results, but the given maxResults is less than 1');
            $this->setErrorCode(self::DATATABLE_MAX_RESULTS_LESS_THAN_ONE);
            return false;
        }
        return $this->realFindRows($theRow, $maxResults);
    }
    
    /**
     * Returns false on error, or an array with results (which could be
     * empty
     */
    abstract public function realFindRows($theRow, $maxResults);
    
    public function findRow($theRow)
    {
        $this->resetError();
        $res = $this->findRows($theRow, 1);
        if ($res === false) {
            // the error code should be set by $this->findRows in this case
            return false;
        }
        
        if ($res === []) {
            $this->setErrorCode(self::DATATABLE_EMPTY_RESULT_SET);
            $this->setErrorMessage('Empty result set when trying to find row');
            return false;
        }
        return $res[0];
    }
    
    /**
     * Updates the table with the given row, which must contain an 'id'
     * field specifying the row to update
     *
     * Only the keys given in $theRow are updated
     *
     * @return boolean
     */
    public function updateRow($theRow)
    {
        $this->resetError();
        if (!isset($theRow['id']))  {
            $this->setErrorMessage('Id not set in given row, cannot update');
            $this->setErrorCode(self::DATATABLE_ID_NOT_SET);
            return false;
        }
            
        if ($theRow['id']===0) {
            $this->setErrorMessage('Id is equal to zero in given row, cannot update');
            $this->setErrorCode(self::DATATABLE_ID_IS_ZERO);
            return false;
        }
        if (!is_int($theRow['id'])) {
            $this->setErrorMessage('Id in given row is not an integer, cannot update');
            $this->setErrorCode(self::DATATABLE_ID_NOT_INTEGER);
            return false;
        }
        if (!$this->rowExistsById($theRow['id'])) {
            $this->setErrorMessage('The row with id ' . $theRow['id'] . ' does not exist, cannot update it');
            $this->setErrorCode(self::DATATABLE_ROW_DOES_NOT_EXIST);
            return false;
        }
        return $this->realUpdateRow($theRow);
    }
    
    /**
     * Get all rows
     * @return array
     */
    abstract public function getAllRows();
    
    /**
     * Gets the row with the given row Id
     *
     * @return array The row
     */
    abstract public function getRow($rowId);
    
    /**
     * @return int a unique id that does not exist in the table
     *             (descendants may want to override the function
     *             and return false if
     *             a unique id can't be determined (which normally should
     *             not happen!)
     *
     */
    public function getOneUnusedId()
    {
        return $this->getMaxId()+1;
    }

    //
    // PROTECTED METHODS
    //
    
    /**
     * @return int the max id in the table
     */
    abstract protected function getMaxId();
    
    
    /**
     * Returns the id of one row in which $row[$key] === $value
     * or false if such a row cannot be found or an error occurred whilst
     * trying to find it.
     * 
     * @return int|bool
     */
    abstract protected function getIdForKeyValue($key, $value);
    
    abstract protected function realCreateRow($theRow);
    abstract protected function realDeleteRow($rowId);
    abstract protected function realUpdateRow($theRow);
    
    
    protected function resetError() 
    {
        $this->setErrorCode(self::DATATABLE_NOERROR);
        $this->setErrorMessage('');
    }
    
    protected function setErrorMessage(string $msg) 
    {
        $this->errorMessage = $msg;
    }
    
    protected function setErrorCode(int $c) 
    {
        $this->errorCode = $c;
    }
    
    
}
