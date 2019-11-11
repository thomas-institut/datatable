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

use InvalidArgumentException;
use \PDO;
use \PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Implements a data table with MySQL
 *
 */
class MySqlDataTable extends DataTable
{
    
    // CONSTANTS
    
    const ERROR_MYSQL_QUERY_ERROR = 1010;
    const ERROR_REQUIRED_COLUMN_NOT_FOUND = 1020;
    const ERROR_WRONG_COLUMN_TYPE = 1030;
    const ERROR_TABLE_NOT_FOUND = 1040;
    const ERROR_INVALID_TABLE = 1050;
    const ERROR_PREPARING_STATEMENTS = 1070;
    const ERROR_EXECUTING_STATEMENT = 1080;
    
    /** @var PDO */
    protected $dbConn;
    
    /**
     *
     * @var string
     */
    protected $tableName;

    /**
     * @var PDOStatement[]
     */
    protected $statements;
    
    /**
     *
     * @param PDO $dbConnection  initialized PDO connection
     * @param string $tableName  SQL table name
     */
    public function __construct(PDO $dbConnection, string $tableName)
    {
        
        parent::__construct();
        
        $this->tableName = $tableName;
        $this->dbConn = $dbConnection;
        
        if (!$this->realIsDbTableValid()) {
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
        }
        
        $this->dbConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       
        // Pre-prepare common statements
        try {
            $this->statements['rowExistsById'] =
                $this->dbConn->prepare('SELECT id FROM ' . $this->tableName 
                        . ' WHERE id= :id');
            $this->statements['deleteRow'] =
                $this->dbConn->prepare('DELETE FROM `' . $this->tableName 
                        . '` WHERE `id`= :id');
        } catch (PDOException $e) { // @codeCoverageIgnore
            // @codeCoverageIgnoreStart
            $msg = "Could not prepare statements in constructor, " . $e->getMessage();
            $errorCode = self::ERROR_PREPARING_STATEMENTS;
            $this->setError($msg, $errorCode);
            throw new RuntimeException($msg, $errorCode);
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Returns true if the table in the DB has
     * at least an id column of type int
     */
    protected function realIsDbTableValid() : bool
    {
        return $this->isMySqlTableColumnValid('id', 'int');
    }
    
    protected function isMySqlTableColumnValid(string $columnName, string $type) : bool
    {
        try {
            $r = $this->dbConn->query(
                'SHOW COLUMNS FROM ' . $this->tableName 
                    . ' LIKE \'' . $columnName . '\''
            );
        } catch (PDOException $e) { // @codeCoverageIgnore
            // @codeCoverageIgnoreStart
            $this->setErrorCode(self::ERROR_MYSQL_QUERY_ERROR);
            $this->setErrorMessage('Query error checking MySQL column ' 
                    . $this->tableName . '::' . $columnName . ' : ' 
                    . $e->getMessage());
            return false;
            // @codeCoverageIgnoreEnd
        }
        
        
        if ($r === false) {
            $this->setErrorCode(self::ERROR_TABLE_NOT_FOUND);
            $this->setErrorMessage('Table ' . $this->tableName . ' not found');
            return false;
        }
        
        if ($r->rowCount() != 1) {
            $this->setErrorCode(self::ERROR_REQUIRED_COLUMN_NOT_FOUND);
            $this->setErrorMessage('Required column ' . $columnName 
                    . ' not found in table ' . $this->tableName);
            return false;
        }
         
        $columnInfo = $r->fetch(PDO::FETCH_ASSOC);
        
        $preg = '/^' . $type . '/';
        if (!preg_match($preg, $columnInfo['Type'])) {
            $this->setErrorCode(self::ERROR_WRONG_COLUMN_TYPE);
            $this->setErrorMessage('Wrong MySQL column type for  ' 
                . $this->tableName . '::' . $columnName 
                . ', required=\'' . $type 
                . '\', actual=\'' . $columnInfo['Type'] . '\'' );
            return false;
        }
        return true;
    }
    
    public function rowExists(int $rowId) : bool
    {
        $this->resetError();

        $this->executeStatement('rowExistsById', ['id' => $rowId]);

        if ($this->statements['rowExistsById']->rowCount() !== 1) {
            return false;
        }

        return true;
    }


    public function realCreateRow(array $theRow) : int
    {
        $keys = array_keys($theRow);
        $sql = 'INSERT INTO `' . $this->tableName . '` (' .
                implode(',', $keys) . ') VALUES ';
        $values = [];
        foreach ($keys as $key) {
            array_push($values, $this->quoteValue($theRow[$key]));
        }
        $sql .= '(' . implode(',', $values) . ');';
        
        $this->doQuery($sql, 'realCreateRow');

        return (int) $theRow['id'];
    }
    
    public function realUpdateRow(array $theRow) : void
    {

        if (!$this->rowExists($theRow['id'])) {
            $this->setErrorCode(self::ERROR_ROW_DOES_NOT_EXIST);
            $this->setErrorMessage('Id ' . $theRow['id'] . ' does not exist, cannot update');
            throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
        }
        $keys = array_keys($theRow);
        $sets = array();
        foreach ($keys as $key) {
            if ($key === 'id') {
                continue;
            }
            array_push($sets, $key . '=' . $this->quoteValue($theRow[$key]));
        }
        $sql = 'UPDATE `' . $this->tableName . '` SET '
                . implode(',', $sets) . ' WHERE `id`=' . $theRow['id'];
        
        $this->doQuery($sql, 'realUpdateRow');

    }

    /**
     * Returns a string with a correctly quoted value for use in MySQL
     *
     * @param $var
     * @return string
     */
    public function quoteValue($var) : string
    {
        if (is_string($var)) {
            return $this->dbConn->quote($var);
        }
        if (is_null($var)) {
            return 'NULL';
        }
        return (string) $var;
    }
    
    public function getAllRows() : array
    {
        $sql  = 'SELECT * FROM ' . $this->tableName;
        
        $r = $this->doQuery($sql, 'getAllRows');

        return $this->forceIntIds($r->fetchAll(PDO::FETCH_ASSOC));
    }
    
    public function getRow(int $rowId) : array
    {
        $sql = 'SELECT * FROM ' . $this->tableName
                . ' WHERE `id`=' . $rowId . ' LIMIT 1';
        
        $r = $this->doQuery($sql, 'getRow');


        $res = $r->fetch(PDO::FETCH_ASSOC);
        if ($res === false) {
            $this->setErrorMessage('The row with id ' 
                    . $rowId . ' does not exist');
            $this->setErrorCode(self::ERROR_ROW_DOES_NOT_EXIST);
            throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
        }
        $res['id'] = (int) $res['id'];
        return $res;
    }
    
    public function getMaxId() : int
    {
        $sql = 'SELECT MAX(id) FROM ' . $this->tableName;
        
        $r = $this->doQuery($sql, 'get MaxId');

        $maxId = $r->fetchColumn();
        if ($maxId === null) {
            return 0;
        }
        return (int) $maxId;
    }
    
    public function getIdForKeyValue(string $key, $value) : int
    {
        $rows = $this->findRows([$key => $value], 1);
        if ($rows === []) {
            $this->setErrorCode(parent::ERROR_KEY_VALUE_NOT_FOUND);
            $this->setErrorMessage('Value ' . $value . ' for key ' . $key .  'not found');
            return self::NULL_ROW_ID;
        }
        return intval($rows[0]['id']);
    }

    /**
     *
     * @param array $theRow
     * @param int $numResults
     * @return array
     */
    public function findRows(array $theRow, int $numResults = 0) : array
    {
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
        $sql = 'SELECT * FROM ' . $this->tableName . ' WHERE ' 
                .  implode(' AND ', $conditions);
        if ($numResults > 0) {
            $sql .= ' LIMIT ' . $numResults;
        }
        
        try {
            $r = $this->dbConn->query($sql);
        } catch (PDOException $e) {
            if ( $e->getCode() === '42000') {
                // The exception was thrown because of an SQL syntax error but
                // this should only happen when one of the keys does not exist
                // or is of the wrong type. This just means that the search
                // did not have any results, so let's set the error code
                // to be 'empty result set'
                // TODO: add an optional full table schema check in order avoid ambiguities here
                $this->setErrorCode(self::ERROR_EMPTY_RESULT_SET);
                // However, just in case this may be hiding something else, 
                // let's report everything in the error message
                $this->setErrorMessage('Query error in realFindRows (reported '
                        . 'as no results) : ' 
                        . $e->getMessage() . ' :: query = ' . $sql);
                return [];
            }
            // @codeCoverageIgnoreStart
            $this->setErrorCode(self::ERROR_MYSQL_QUERY_ERROR);
            $this->setErrorMessage('Query error in realFindRows: ' 
                    . $e->getMessage() . ' :: query = ' . $sql);
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
            // @codeCoverageIgnoreEnd
        }
        
        if ($r === false) {
            // @codeCoverageIgnoreStart
            $this->setErrorCode(self::ERROR_UNKNOWN_ERROR);
            $this->setErrorMessage('Unknown error in realFindRows '
                    . 'when executing query: ' . $sql);
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
            // @codeCoverageIgnoreEnd
        }
        

        return $this->forceIntIds($r->fetchAll(PDO::FETCH_ASSOC));
    }
    
    public function deleteRow(int $rowId) : bool
    {
        $this->executeStatement('deleteRow', [':id' => $rowId]);

        if ($this->statements['deleteRow']->rowCount() !== 1) {
            // this can only mean that the row to delete did not exst
            return false;
        }
        return true;


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
    
    protected function doQuery(string $sql, string $context) : PDOStatement
    {
        try {
            $r = $this->dbConn->query($sql);
         } catch (PDOException $e) {
            $this->setErrorCode(self::ERROR_MYSQL_QUERY_ERROR);
            $this->setErrorMessage('Query error in "' . $context . '" : "' 
                    . $e->getMessage() . '", query = "' . $sql . '"');
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
        }
        if ($r === false) {
            // @codeCoverageIgnoreStart
            $this->setErrorCode(self::ERROR_UNKNOWN_ERROR);
            $this->setErrorMessage('Unknown error in "' . $context 
                    . '" when executing query: ' . $sql);
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
            // @codeCoverageIgnoreEnd
        }
        
        return $r;
    }

    /**
     * Executes a named prepared statement,
     * if there's any problem throws a Runtime exception
     *
     * @param string $statement
     * @param array $param
     * @throws RuntimeException
     */
    protected function executeStatement(string $statement, array $param) : void
    {
        try {
            $result = $this->statements[$statement]->execute($param);
        } catch (PDOException $e) {
            $this->setErrorCode(self::ERROR_MYSQL_QUERY_ERROR);
            $this->setErrorMessage('MySQL error when executing ' 
                . 'prepared statement "' . $statement . '": ' 
                . $e->getMessage());
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
        }
        
        if ($result === false) {
            // @codeCoverageIgnoreStart
            $this->setErrorCode(self::ERROR_EXECUTING_STATEMENT);
            $this->setErrorMessage('Unknown error when executing ' . 
                    'prepared statement "' . $statement . '"');
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
            // @codeCoverageIgnoreEnd
        }
    }
}
