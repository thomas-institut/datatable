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
        $this->dbConn = $theDb;
        $this->tableName = $tableName;
        
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
    protected function realIsDbTableValid()
    {
        $result = $this->dbConn->query(
            'SHOW COLUMNS FROM ' . $this->tableName . ' LIKE \'id\''
        );
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
    
    public function rowExistsById($rowId)
    {
        if (!$this->isDbTableValid()) {
            return false;
        }
        if ($this->statements['rowExistsById']->execute(['id' => $rowId])) {
            return $this->statements['rowExistsById']->rowCount() === 1;
        }
        // can't get here in testing
        return false; // @codeCoverageIgnore
    }
    
    public function realCreateRow($theRow)
    {
        if (!$this->isDbTableValid()) {
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
        if ($this->dbConn->query($sql) === false) {
            //print("Can't create, query:  $sql; error info: " .
            //        $this->dbConn->errorInfo()[2]);
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
        //error_log("Executing query: $sql");
        if ($this->dbConn->query($sql) === false) {
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
            return false;
        }
        $res = $this->dbConn->query('SELECT * FROM ' . $this->tableName);
        if ($res === false) {
            // Can't get here in testing: query only fails on MySQL failure
            return false; // @codeCoverageIgnore
        }
        return $this->forceIntIds($res->fetchAll(PDO::FETCH_ASSOC));
    }
    
    public function getRow($rowId)
    {
        if (!$this->isDbTableValid()) {
            return false;
        }
        
        $res = $this->dbConn
                ->query('SELECT * FROM ' . $this->tableName .
                        ' WHERE `id`=' . $rowId . ' LIMIT 1')
                ->fetch(PDO::FETCH_ASSOC);
        if ($res === false) {
            // Can't get here in testing: query only fails on MySQL failure
            return false; // @codeCoverageIgnore
        }
        $res['id'] = (int) $res['id'];
        return $res;
    }
    
    public function getMaxId()
    {
        if (!$this->isDbTableValid()) {
            return false;
        }
        $query = 'SELECT MAX(id) FROM ' . $this->tableName;
        $res = $this->dbConn->query($query);
        if ($res === false) {
            //print("Query returned false: $query\n");
            // Can't get here in testing: query only fails on MySQL failure
            return false; // @codeCoverageIgnore
        }
        $maxId = $res->fetchColumn();
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
        $res = $this->dbConn->query($sql);
        if ($res === false) {
            return false;
        }

        return $this->forceIntIds($res->fetchAll(PDO::FETCH_ASSOC));
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
