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
 * 
 * By default each row must have a unique int key: 'id' 
 * The assignment of IDs is left to the class, not to the underlying
 * database.
 *
 * @author Rafael Nájera <rafael@najera.ca>
 */
abstract class DataTable {
    
    //
    // PUBLIC METHODS
    //
    
    /**
     * @return bool true if the row with the given Id exists
     */
    abstract public function rowExistsById($rowId);
    
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
    public function createRow($theRow){
        if (!isset($theRow['id']) || $theRow['id']===0){
            $theRow['id'] = $this->getOneUnusedId();
            if ($theRow['id'] === false){
                return false;
            }
        }
        else {
            if (!is_int($theRow['id'])){
                return false;
            }
            if ($this->rowExistsById($theRow['id'])){
                return false;
            }
        }
        return $this->realCreateRow($theRow);
    }
    
    /**
     * @return bool true if the row was deleted (or if the row did not 
     *              exist in the first place;
     */
    public function deleteRow($rowId){
        if (!$this->rowExistsById($rowId)){
            return true;
        }
        return $this->realDeleteRow($rowId);
    }
    
    /**
     * Searches the table for a row with the same data as the given row
     * 
     * Only the keys given in $theRow are checked; so, for example,
     * if $theRow is missing a key that exists in the actual rows
     * in the table, those missing keys are ignored and the method
     * will return any row that matches exactly the given keys independently
     * of the missing ones.
     * 
     * @return int the Row Id 
     */
    abstract public function findRow($theRow);
    
    /**
     * Updates the table with the given row, which must contain an 'id'
     * field specifying the row to update
     * 
     * Only the keys given in $theRow are updated; 
     * 
     * @return boolean
     */
    public function updateRow($theRow){
        if (!isset($theRow['id']) || $theRow['id']===0){
            //error_log("Can't update, no id set: " . $theRow['id'] ."\n");
            return false;
        }
        else {
            if (!is_int($theRow['id'])){
                //error_log("Can't update, id is not int: " . $theRow['id'] . " is " . gettype($theRow['id']) . "\n");
                return false;
            }
            if (!$this->rowExistsById($theRow['id'])){
                //error_log("Can't update, row exists: " . $theRow['id'] ."\n");
                return false;
            }
        }
        return $this->realUpdateRow($theRow);
    }
    
    
    /** 
     * Get all rows
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
    public function getOneUnusedId(){
        return $this->getMaxId()+1;
    }
    

    //
    // ABSTRACT PROTECTED METHODS
    //
    
    /**
     * @return int the max id in the table
     */
    abstract protected function getMaxId();
    
    abstract protected function getIdForKeyValue($key, $value);
    abstract protected function realCreateRow($theRow);
    abstract protected function realDeleteRow($rowId);
    abstract protected function realUpdateRow($theRow);
 }
