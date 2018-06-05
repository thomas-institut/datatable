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
require '../vendor/autoload.php';

use \PDO;
use \PDOException;

/**
 * Implements a data table with MySQL
 *
 */
class MySqlDataTable extends DataTable
{
    
    // CONSTANTS
    
    const MYSQL_DATATABLE_QUERY_ERROR = 1010;
    const MYSQL_DATATABLE_ID_COLUMN_NOT_FOUND = 1020;
    const MYSQL_DATATABLE_ID_COLUMN_NOT_INT = 1030;
    const MYSQL_DATATABLE_TABLE_NOT_FOUND = 1040;
    const MYSQL_DATATABLE_INVALID_TABLE = 1050;
    
    /** @var PDO */
    protected $dbConn;
    
    /**
     *
     * @var string
     */
    protected $tableName;
    
    protected $statements;
    
    /**
     *
     * @var boolean
     */
    private $validDbTable;
    
    /**
     *
     * @param \PDO $theDb  initialized PDO connection
     * @param string $tableName  SQL table name
     */
    public function __construct($theDb, $tableName)
    {
        
        parent::__construct();
        
        $this->dbConn = $theDb;
        $this->tableName = $tableName;
        
        $this->validDbTable = $this->realIsDbTableValid();
        if (!$this->validDbTable) {
            return;
        }
        
        $this->dbConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       
        // Pre-prepare common statements
        // TODO: check and test for errors in preparing these statements
        $this->statements['rowExistsById'] =
                $this->dbConn->prepare('SELECT id FROM ' . $this->tableName .
                        ' WHERE id= :id');
        $this->statements['deleteRow'] =
                $this->dbConn->prepare('DELETE FROM `' . $this->tableName .
                        '` WHERE `id`= :id');
    }
    
    public function isDbTableValid()
    {
        return $this->validDbTable;
    }
    
    /**
     * Returns true if the table in the DB has
     * at least an id column of type int
     */
    protected function realIsDbTableValid()
    {
        try {
            $r = $this->dbConn->query(
                'SHOW COLUMNS FROM ' . $this->tableName . ' LIKE \'id\''
            );
        } catch (PDOException $e) {
            $this->setErrorCode(self::MYSQL_DATATABLE_QUERY_ERROR);
            $this->setErrorMessage('Query error: ' . $e->getMessage());
            return false;
        }
        if ($r === false) {
            $this->setErrorCode(self::MYSQL_DATATABLE_TABLE_NOT_FOUND);
            $this->setErrorMessage('Table ' . $this->tableName . ' not found');
            return false;
        }
        
        if ($r->rowCount() != 1) {
            $this->setErrorCode(self::MYSQL_DATATABLE_ID_COLUMN_NOT_FOUND);
            $this->setErrorMessage('No id column in MySQL table ' . $this->tableName);
            return false;
        }
         
        $columnInfo = $r->fetch(PDO::FETCH_ASSOC);
         
        if (preg_match('/^int/', $columnInfo['Type'])) {
            return true;
        }

        $this->setErrorCode(self::MYSQL_DATATABLE_ID_COLUMN_NOT_INT);
        $this->setErrorMessage('id column not integer in MySQL table ' . $this->tableName . '. Actual type: ' .$columnInfo['Type'] );
        return false;
    }
    
    public function rowExistsById($rowId)
    {
        $this->resetError();
        
        if (!$this->isDbTableValid()) {
            $this->setErrorCode(self::MYSQL_DATATABLE_INVALID_TABLE);
            $this->setErrorMessage('Table was found to be invalid at creation time, aborting rowExistsById');
            return false;
        }
        try {
            $r = $this->statements['rowExistsById']->execute(['id' => $rowId]);
        } catch (PDOException $e) {
            $this->setErrorCode(self::MYSQL_DATATABLE_QUERY_ERROR);
            $this->setErrorMessage('MySQL error when executing rowExistById prepared statement: ' . $e->getMessage());
            return false;
        }
        
        if ($r === false) {
            $this->setErrorCode(self::DATATABLE_UNKNOWN_ERROR);
            $this->setErrorMessage('Unknown error when executing rowExistById prepared statement');
            return false;
        }
        
        if ($this->statements['rowExistsById']->rowCount() === 1) {
            return true;
        }
        
        $this->setErrorCode(self::DATATABLE_ROW_DOES_NOT_EXIST);
        $this->setErrorMessage('Row with id ' . $rowId . ' does not exist');
        return false;
    }
    
    public function realCreateRow($theRow)
    {
        if (!$this->isDbTableValid()) {
            $this->setErrorCode(self::MYSQL_DATATABLE_INVALID_TABLE);
            $this->setErrorMessage('Table was found to be invalid at creation time, aborting realCreateRow');
            return false;
        }
        $keys = array_keys($theRow);
        $sql = 'INSERT INTO `' . $this->tableName . '` (' .
                implode(',', $keys) . ') VALUES ';
        $values = [];
        foreach ($keys as $key) {
            array_push($values, $this->quote($theRow[$key]));
        }
        $sql .= '(' . implode(',', $values) . ');';
        try {
            $r = $this->dbConn->query($sql);
         } catch (PDOException $e) {
            $this->setErrorCode(self::MYSQL_DATATABLE_QUERY_ERROR);
            $this->setErrorMessage('Query error in realCreateRow: ' . $e->getMessage() . ' :: query = ' . $sql);
            return false;
        }
        if ($r === false) {
            $this->setErrorCode(self::DATATABLE_UNKNOWN_ERROR);
            $this->setErrorMessage('Unknown error in realCreateRow when executing query: ' . $sql);
            return false;
        }
        return (int) $theRow['id'];
    }
    
    public function realUpdateRow($theRow)
    {
        $keys = array_keys($theRow);
        $sets = array();
        foreach ($keys as $key) {
            if ($key === 'id') {
                continue;
            }
            array_push($sets, $key . '=' . $this->quote($theRow[$key]));
        }
        $sql = 'UPDATE ' . $this->tableName . ' SET ' .
                implode(',', $sets) . ' WHERE id=' . $theRow['id'];
        
        try {
            $r = $this->dbConn->query($sql);
         } catch (PDOException $e) {
            $this->setErrorCode(self::MYSQL_DATATABLE_QUERY_ERROR);
            $this->setErrorMessage('Query error in realUpdateRow: ' . $e->getMessage() . ' :: query = ' . $sql);
            return false;
        }
        if ($r === false) {
            $this->setErrorCode(self::DATATABLE_UNKNOWN_ERROR);
            $this->setErrorMessage('Unknown error in realUpdate row when executing query: ' . $sql);
            return false;
        }

        return $theRow['id'];
    }
    
    public function quote($var)
    {
        if (is_string($var)) {
            return $this->dbConn->quote($var);
        }
        if (is_null($var)) {
            return 'NULL';
        }
        return (string) $var;
    }
    
    public function getAllRows()
    {
        if (!$this->isDbTableValid()) {
            $this->setErrorCode(self::MYSQL_DATATABLE_INVALID_TABLE);
            $this->setErrorMessage('Table was found to be invalid at creation time, aborting getAllRows');
            return false;
        }
        
        $sql  = 'SELECT * FROM ' . $this->tableName;
        try {
            $r = $this->dbConn->query($sql);
         } catch (PDOException $e) {
            $this->setErrorCode(self::MYSQL_DATATABLE_QUERY_ERROR);
            $this->setErrorMessage('Query error in getAllRows: ' . $e->getMessage() . ' :: query = ' . $sql);
            return false;
        }
        if ($r === false) {
            $this->setErrorCode(self::DATATABLE_UNKNOWN_ERROR);
            $this->setErrorMessage('Unknown error in getAllRows when executing query: ' . $sql);
            return false;
        }
        
        return $this->forceIntIds($r->fetchAll(PDO::FETCH_ASSOC));
    }
    
    public function getRow($rowId)
    {
        if (!$this->isDbTableValid()) {
            $this->setErrorCode(self::MYSQL_DATATABLE_INVALID_TABLE);
            $this->setErrorMessage('Table was found to be invalid at creation time, aborting getRow');
            return false;
        }
        
        $sql = 'SELECT * FROM ' . $this->tableName . ' WHERE `id`=' . $rowId . ' LIMIT 1';
        try {
            $r = $this->dbConn->query($sql);
         } catch (PDOException $e) {
            $this->setErrorCode(self::MYSQL_DATATABLE_QUERY_ERROR);
            $this->setErrorMessage('Query error in getAllRows: ' . $e->getMessage() . ' :: query = ' . $sql);
            return false;
        }
        if ($r === false) {
            $this->setErrorCode(self::DATATABLE_UNKNOWN_ERROR);
            $this->setErrorMessage('Unknown error in getAllRows when executing query: ' . $sql);
            return false;
        }
        
        $res = $r->fetch(PDO::FETCH_ASSOC);
        if ($res === false) {
            $this->setErrorMessage('The row with id ' . $rowId . ' does not exist');
            $this->setErrorCode(self::DATATABLE_ROW_DOES_NOT_EXIST);
            return false;
        }
        $res['id'] = (int) $res['id'];
        return $res;
    }
    
    public function getMaxId()
    {
        if (!$this->isDbTableValid()) {
            $this->setErrorCode(self::MYSQL_DATATABLE_INVALID_TABLE);
            $this->setErrorMessage('Table was found to be invalid at creation time, aborting getMaxId');
            return false;
        }
        $sql = 'SELECT MAX(id) FROM ' . $this->tableName;
        try {
            $r = $this->dbConn->query($sql);
         } catch (PDOException $e) {
            $this->setErrorCode(self::MYSQL_DATATABLE_QUERY_ERROR);
            $this->setErrorMessage('Query error: ' . $e->getMessage() . ' :: query = ' . $sql);
            return false;
        }
        if ($r === false) {
            $this->setErrorCode(self::DATATABLE_UNKNOWN_ERROR);
            $this->setErrorMessage('Unknown error when executing query: ' . $sql);
            return false;
        }
      
        $maxId = $r->fetchColumn();
        if ($maxId === null) {
            return 0;
        }
        return (int) $maxId;
    }
    
    public function getIdForKeyValue($key, $value)
    {
        $row = $this->findRow([$key => $value]);
        if ($row === false) {
            return false;
        }
        return $row['id'];
    }
    
    /**
     *
     * @param array $theRow
     * @param int $maxResults
     * @return int/array if $maxResults == 1, returns a single int, if not,
     *                   returns an array of ints. Returns false if not
     *                   rows are found
     */
    public function realfindRows($theRow, $maxResults)
    {
        if (!$this->isDbTableValid()) {
            $this->setErrorCode(self::MYSQL_DATATABLE_INVALID_TABLE);
            $this->setErrorMessage('Table was found to be invalid at creation time, aborting realfindRows');
            return false;
        }
        $keys = array_keys($theRow);
        $conditions = [];
        foreach ($keys as $key) {
            $c = $key . '=';
            if (is_string($theRow[$key])) {
                $c .= $this->dbConn->quote($theRow[$key]);
            } else {
                $c .= $theRow[$key];
            }
            $conditions[] = $c;
        }
        $sql = 'SELECT * FROM ' . $this->tableName . ' WHERE ' .
                implode(' AND ', $conditions);
        if ($maxResults) {
            $sql .= ' LIMIT ' . $maxResults;
        }
        
        try {
            $r = $this->dbConn->query($sql);
         } catch (PDOException $e) {
             if ( $e->getCode() === '42000') {
                 // The exception was thrown because of an SQL syntax error but
                 // this should only happen when one of the keys does not exist or
                 // is of the wrong type. This just means that the search
                 // did not have any results, so let's set the error code
                 // to be 'empty result set'
                 $this->setErrorCode(self::DATATABLE_EMPTY_RESULT_SET);
                 // However, just in case this may be hiding something else, 
                 // let's report everything in the error message
                 $this->setErrorMessage('Query error in realFindRows (reported as no results) : ' . 
                         $e->getMessage() . ' :: query = ' . $sql);
                 return false;
             }
             
            $this->setErrorCode(self::MYSQL_DATATABLE_QUERY_ERROR);
            $this->setErrorMessage('Query error in realFindRows: ' . $e->getMessage() . ' :: query = ' . $sql);
            return false;
        }
        if ($r === false) {
            $this->setErrorCode(self::DATATABLE_UNKNOWN_ERROR);
            $this->setErrorMessage('Unknown error when executing query: ' . $sql);
            return false;
        }

        return $this->forceIntIds($r->fetchAll(PDO::FETCH_ASSOC));
    }
    
    public function realDeleteRow($rowId)
    {
        return $this->statements['deleteRow']
                ->execute([':id' => $rowId]) !== false;
    }
    
    protected function forceIntIds($theRows)
    {
        $rows = $theRows;
        for ($i = 0; $i < count($rows); $i++) {
            if (!is_int($rows[$i]['id'])) {
                $rows[$i]['id'] = (int) $rows[$i]['id'];
            }
        }
        return $rows;
    }
}
