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

use \PDO;

/**
 * Implements a data table with MySQL
 * 
 */
class MySqlDataTable extends DataTable
{
    
    /** @var PDO */
    protected $dbConn;
    protected $tableName;
    protected $statements;
    
    private $validDbTable;
    
    /**
     * 
     * @param \PDO $theDb  initialized PDO connection
     * @param string $tn  SQL table name
     */
    public function __construct($theDb, $tn) {
        $this->dbConn = $theDb;
        $this->dbConn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        $this->tableName = $tn;
        
        $this->validDbTable = $this->realIsDbTableValid();
        if (!$this->validDbTable) {
            return;
        }
       
        // Pre-prepare common statements
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
    private function realIsDbTableValid()
    {
         $result = $this->dbConn->query(
                 'SHOW COLUMNS FROM ' . $this->tableName . ' LIKE \'id\'');
         if ($result === false) {
             // This means that the table does not exist!
             return false;
         }
         
         if ($result->rowCount() != 1) {
             return false;
         }
         
         $columnInfo = $result->fetch(PDO::FETCH_ASSOC);
         
         if (preg_match('/^int/', $columnInfo['Type'])) {
             return true;
         }
         
         return false;
         
    }
    
    public function rowExistsById($rowId) {
        if (!$this->isDbTableValid()) {
            return false;
        }
        if ($this->statements['rowExistsById']->execute(['id' => $rowId])){
            return $this->statements['rowExistsById']->rowCount() === 1;
        }
        // can't get here in testing
        return false; // @codeCoverageIgnore  
    }
    
    public function realCreateRow($theRow) {
        if (!$this->isDbTableValid()) {
            return false;
        }
        $keys = array_keys($theRow);
        $sql = 'INSERT INTO `' . $this->tableName . '` (' . 
                implode(',', $keys) . ') VALUES ';
        $values = [];
        foreach($keys as $key){
            array_push($values, $this->quote($theRow[$key]));
        }
        $sql .= '(' . implode(',', $values) . ');';
        if ($this->dbConn->query($sql) === FALSE) {
//            error_log("Can't create, query:  $sql; error info: " . 
//                    $this->db->errorInfo()[2]);
            return false;
        }
        return $theRow['id'];
    }
    
    public function realUpdateRow($theRow)
    {
        $keys = array_keys($theRow);
        $sets = array();
        foreach($keys as $key){
            if ($key === 'id'){
                continue;
            }
            array_push($sets, $key . '=' . $this->quote($theRow[$key]));
        }
        
        $sql = 'UPDATE ' . $this->tableName . ' SET ' . 
                implode(',', $sets) . ' WHERE id=' . $theRow['id'];
        //error_log("Executing query: $sql");
        if ($this->dbConn->query($sql) === false) {
            return false;
        }
        return $theRow['id'];
    }
    
    public function quote($v)
    {
        if (is_string($v)) {
            return $this->dbConn->quote($v);
        }
        if (is_null($v)) {
            return 'NULL';
        }
        return (string) $v;
    }
    
    public function getAllRows() 
    {
        if (!$this->isDbTableValid()) {
            return false;
        }
        $r = $this->dbConn->query('SELECT * FROM ' . $this->tableName);
        if ($r === false) {
            // Can't get here in testing: query only fails on MySQL failure
            return false; // @codeCoverageIgnore  
        }
        return $r->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getRow($rowId) {
        if (!$this->isDbTableValid()) {
            return false;
        }
        
        $r = $this->dbConn
                ->query('SELECT * FROM ' . $this->tableName . 
                        ' WHERE `id`=' . $rowId . ' LIMIT 1')
                ->fetch(PDO::FETCH_ASSOC);
        if ($r === false) {
            // Can't get here in testing: query only fails on MySQL failure
            return false; // @codeCoverageIgnore  
        }
        $r['id'] = (int) $r['id'];
        return $r;
        
    }
    
    public function getMaxId() {
        if (!$this->isDbTableValid()) {
            return false;
        }
        $query = 'SELECT MAX(id) FROM ' . $this->tableName;
        $r = $this->dbConn->query($query);
        if ($r === false) {
            //print("Query returned false: $query\n");
            // Can't get here in testing: query only fails on MySQL failure
            return false; // @codeCoverageIgnore  
        }
        $maxId = $r->fetchColumn();
        if ($maxId === null) {
            return 0;
        }
        return (int) $maxId;
    }
    
    public function getIdForKeyValue($key, $value) {
        return $this->findRow([$key => $value]);
    }
    
    /**
     * 
     * @param array $theRow
     * @param int $maxResults  
     * @return int/array if $maxResults == 1, returns a single int, if not, 
     *                   returns an array of ints. Returns false if not
     *                   rows are found
     */
    public function findRows($theRow, $maxResults = false)
    {
        if (!$this->isDbTableValid()) {
            return false;
        }
        $keys = array_keys($theRow);
        $conditions = [];
        foreach ($keys as $key){
            $c = $key . '=';
            if (is_string($theRow[$key])) {
                $c .= $this->dbConn->quote($theRow[$key]);
            } 
            else {
                $c .= $theRow[$key];
            }
            $conditions[] = $c;
        }
        $sql = 'SELECT id FROM ' . $this->tableName . ' WHERE ' . 
                implode(' AND ', $conditions);
        if ($maxResults){
            $sql .= ' LIMIT ' . $maxResults;
        }
        $r = $this->dbConn->query($sql);
        if ( $r === false) {
            return false;
        }
        if ($maxResults == 1){
            $theId = (int) $r->fetchColumn();
            if ($theId == 0){
                return false;
            }
            return $theId;
        }
        $ids = [];
        while ($id = (int) $r->fetchColumn()) {
            $ids[] = $id;
        }
        if (count($ids)== 0){
            return false;
        }
        return $ids;
    }
    
    public function findRow($theRow)
    {
        return $this->findRows($theRow, 1);
    }
    
    public function realDeleteRow($rowId)
    {
        return $this->statements['deleteRow']
                ->execute([':id' => $rowId]) !== false;
    }
}

