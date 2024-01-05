<?php

/*
 * The MIT License
 *
 * Copyright 2017-24 Thomas-Institut, Universität zu Köln.
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
namespace ThomasInstitut\DataTable;

use InvalidArgumentException;
use LogicException;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Implements a data table with MySQL
 *
 */
class MySqlDataTable extends GenericDataTable
{
    
    // CONSTANTS
    
    const ERROR_MYSQL_QUERY_ERROR = 1010;
    const ERROR_REQUIRED_COLUMN_NOT_FOUND = 1020;
    const ERROR_WRONG_COLUMN_TYPE = 1030;
    const ERROR_TABLE_NOT_FOUND = 1040;
    const ERROR_PREPARING_STATEMENTS = 1070;
    const ERROR_EXECUTING_STATEMENT = 1080;

    const ERROR_INVALID_WHERE_CLAUSE = 1090;

    protected PDO $dbConn;

    /**
     * @var PDOStatement[]
     */
    protected array $statements;
    private bool $mySqlAutoInc;

    /**
     *
     * @param PDO $dbConnection  initialized PDO connection
     * @param string $tableName  SQL table name
     */
    public function __construct(PDO $dbConnection, string $tableName, bool $useMySqlAutoInc = false, string $idColumnName = self::DEFAULT_ID_COLUMN_NAME)
    {
        
        parent::__construct();
        
        $this->tableName = $tableName;
        $this->idColumnName = $idColumnName;
        $this->dbConn = $dbConnection;
        $this->mySqlAutoInc = $useMySqlAutoInc;
        
        if (!$this->isMySqlTableColumnValid($this->idColumnName, 'int')) {
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
        }
        
        $this->dbConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       
        // Pre-prepare common statements
        try {
            $this->statements['rowExistsById'] =
                $this->dbConn->prepare('SELECT `' . $this->idColumnName .  '` FROM `' . $this->tableName
                        . '` WHERE '. $this->idColumnName .  '= :id');
            $this->statements['deleteRow'] =
                $this->dbConn->prepare('DELETE FROM `' . $this->tableName
                    . '` WHERE `'. $this->idColumnName .  '`= :id');
        } catch (PDOException $e) { // @codeCoverageIgnore
            // @codeCoverageIgnoreStart
            $msg = "Could not prepare statements in constructor, " . $e->getMessage();
            $errorCode = self::ERROR_PREPARING_STATEMENTS;
            $this->setError($msg, $errorCode);
            throw new RuntimeException($msg, $errorCode);
            // @codeCoverageIgnoreEnd
        }
    }


    protected function isMySqlTableColumnValid(string $columnName, string $type) : bool
    {
        try {
            $r = $this->dbConn->query(
                'SHOW COLUMNS FROM ' . $this->tableName 
                    . ' LIKE \'' . $columnName . '\''
            );
        } catch (PDOException $e) { // @codeCoverageIgnore
            if ($e->getCode() === '42S02') {
                $this->setError('Table ' . $this->tableName . ' not found',
                    self::ERROR_TABLE_NOT_FOUND);
                return false;
            }
            // @codeCoverageIgnoreStart
            $this->setError('Query error checking MySQL column '
                    . $this->tableName . '::' . $columnName . ' : MySql Error Code ' . $e->getCode() . ", msg = ". $e->getMessage(),
                self::ERROR_MYSQL_QUERY_ERROR);
            return false;
            // @codeCoverageIgnoreEnd
        }
        
        
        if ($r === false) {
            $this->setError('Table ' . $this->tableName . ' not found',
                self::ERROR_TABLE_NOT_FOUND);
            return false;
        }
        
        if ($r->rowCount() != 1) {
            $this->setError('Required column ' . $columnName  . ' not found in table ' . $this->tableName,
                self::ERROR_REQUIRED_COLUMN_NOT_FOUND);
            return false;
        }
         
        $columnInfo = $r->fetch(PDO::FETCH_ASSOC);
        
        $preg = '/^' . $type . '/';
        if (!preg_match($preg, $columnInfo['Type'])) {
            $this->setError('Wrong MySQL column type for  '
                . $this->tableName . '::' . $columnName
                . ', required=\'' . $type
                . '\', actual=\'' . $columnInfo['Type'] . '\'',
                self::ERROR_WRONG_COLUMN_TYPE);
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

    public function createRow(array $theRow) : int {
        if (!$this->mySqlAutoInc) {
            // use regular id creator
            return parent::createRow($theRow);
        }
        if (!isset($theRow[$this->idColumnName]) || !is_int($theRow[$this->idColumnName])) {
            $theRow[$this->idColumnName] = 0;
        }
        if ($theRow[$this->idColumnName] !== 0) {
            if ($this->rowExists($theRow[$this->idColumnName])) {
                $this->setError('The row with given id ('. $theRow[$this->idColumnName] . ') already exists, cannot create',
                    self::ERROR_ROW_ALREADY_EXISTS);
                throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
            }
        }
        $this->doQuery($this->getMySqlInsertQuery($theRow), 'createRow_MySQL_auto_inc');
        return $this->dbConn->lastInsertId();
    }

    private function getMySqlInsertQuery(array $theRow): string
    {
        $keys = array_keys($theRow);
        $sql = 'INSERT INTO `' . $this->tableName . '` (' .
            implode(',', $keys) . ') VALUES ';
        $values = [];
        foreach ($keys as $key) {
            $values[] = $this->quoteValue($theRow[$key]);
        }
        $sql .= '(' . implode(',', $values) . ');';

        return $sql;
    }


    public function realCreateRow(array $theRow) : int
    {
        $this->doQuery($this->getMySqlInsertQuery($theRow), 'realCreateRow');
        return (int) $theRow[$this->idColumnName];
    }
    
    public function realUpdateRow(array $theRow) : void
    {

        if (!$this->rowExists($theRow[$this->idColumnName])) {
            $this->setError('Id ' . $theRow[$this->idColumnName] . ' does not exist, cannot update',
                self::ERROR_ROW_DOES_NOT_EXIST );
            throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
        }
        $keys = array_keys($theRow);
        $sets = [];
        foreach ($keys as $key) {
            if ($key === $this->idColumnName) {
                continue;
            }
            $sets[] = $key . '=' . $this->quoteValue($theRow[$key]);
        }
        $sql = 'UPDATE `' . $this->tableName . '` SET '
                . implode(',', $sets) . ' WHERE `' . $this->idColumnName . '`=' . $theRow[$this->idColumnName];
        
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

    /**
     * Executes a general SELECT query on the data table
     *
     * ATTENTION: Try not to use this method directly , prefer to use 'search' if possible,
     * search is implemented by any DataTable not just by MySqlDataTable
     *
     * The method executes the following query:
     *  SELECT * FROM tableName WHERE  $where LIMIT $limit ORDER BY $orderBy
     *
     * LIMIT $limit is omitted if $limit < 0
     * ORDER BY $orderBy is omitted if $orderBy=== ''
     *
     * $context is used to report errors
     *
     * @param string $what
     * @param string $where
     * @param int $limit
     * @param string $orderBy
     * @param string $context
     * @return PDOStatement
     */
    public function select(string $what, string $where, int $limit, string $orderBy, string $context ) : PDOStatement {

        if ($what === '') {
            $what = '*';
        }
        if ($where ==='') {
            $this->setError('Empty where clause', self::ERROR_INVALID_WHERE_CLAUSE);
            throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
        }

        $sql = 'SELECT ' .  $what . ' FROM `' . $this->tableName . '` WHERE ' . $where;
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }
        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        return $this->doQuery($sql, $context);
    }
    
    public function getRow(int $rowId) : array
    {
        $r = $this->select('*', $this->idColumnName . '=' . $rowId, 1, '', 'getRow');

        $res = $r->fetch(PDO::FETCH_ASSOC);
        if ($res === false) {
            $this->setError('The row with id ' . $rowId . ' does not exist',self::ERROR_ROW_DOES_NOT_EXIST );
            throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
        }
        $res[$this->idColumnName] = (int) $res[$this->idColumnName];
        return $res;
    }

    public function getMaxValueInColumn(string $columnName): int
    {
        $sql = 'SELECT MAX('. $columnName . ') FROM ' . $this->tableName;

        $r = $this->doQuery($sql, 'getMaxValueInColumn');

        $maxId = $r->fetchColumn();
        if ($maxId === null) {
            return 0;
        }
        return (int) $maxId;
    }

    public function getMaxId() : int
    {
        return $this->getMaxValueInColumn($this->idColumnName);

    }
    
    public function getIdForKeyValue(string $key, mixed $value) : int
    {
        $rows = $this->findRows([$key => $value], 1);
        if ($rows === []) {
            $this->setError('Value ' . $value . ' for key ' . $key .  'not found', self::ERROR_KEY_VALUE_NOT_FOUND);
            return self::NULL_ROW_ID;
        }
        return intval($rows[0][$this->idColumnName]);
    }

    public function deleteRow(int $rowId) : int
    {
        $this->executeStatement('deleteRow', ['id' => $rowId]);

        if ($this->statements['deleteRow']->rowCount() !== 1) {
            // this can only mean that the row to delete did not exist
            return 0;
        }
        return 1;


    }
    
    protected function forceIntIds($theRows)
    {
        $rows = $theRows;
        for ($i = 0; $i < count($rows); $i++) {
            if (!is_int($rows[$i][$this->idColumnName])) {
                $rows[$i][$this->idColumnName] = (int) $rows[$i][$this->idColumnName];
            }
        }
        return $rows;
    }
    
    protected function doQuery(string $sql, string $context) : PDOStatement
    {
        try {
            $r = $this->dbConn->query($sql);
         } catch (PDOException $e) {
            $this->setError('Query error in "' . $context . '" : "'
                . $e->getMessage() . '", query = "' . $sql . '"',
                self::ERROR_MYSQL_QUERY_ERROR
                );
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
        }
        if ($r === false) {
            // @codeCoverageIgnoreStart
            $this->setError('Unknown error in "' . $context
                . '" when executing query: ' . $sql, self::ERROR_UNKNOWN_ERROR);
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
            // @codeCoverageIgnoreEnd
        }
        
        return $r;
    }

    public function getUniqueIds(): array
    {
        $tableName = $this->tableName;
        $idColumn = $this->idColumnName;
        $result = $this->doQuery("SELECT DISTINCT($idColumn) FROM `$tableName` ORDER BY `$tableName`.`$idColumn`", "getUniqueIds");
        $ids = array_map( function ($row) use ($idColumn): int { return intval($row[$idColumn]);}, $result->fetchAll());
        sort($ids, SORT_NUMERIC);
        return $ids;
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
            $this->setError('MySQL error when executing '
                . 'prepared statement "' . $statement . '": '
                . $e->getMessage(), self::ERROR_MYSQL_QUERY_ERROR);
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
        }
        
        if ($result === false) {
            // @codeCoverageIgnoreStart
            $this->setError('Unknown error when executing ' .
                'prepared statement "' . $statement . '"',self::ERROR_EXECUTING_STATEMENT );
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
            // @codeCoverageIgnoreEnd
        }
    }


    public function search(array $searchSpecArray, int $searchType = self::SEARCH_AND, int $maxResults = 0): array
    {
       $this->checkSpec($searchSpecArray, $searchType);

        $conditions = [];
        foreach ($searchSpecArray as $spec) {
            $conditions[] = $this->getSqlConditionFromSpec($spec);
        }

        $sqlLogicalOperator  = 'AND';
        if ($searchType == self::SEARCH_OR) {
            $sqlLogicalOperator = 'OR';
        }

        $sql = 'SELECT * FROM `' . $this->tableName . '` WHERE '
            .  implode(' ' . $sqlLogicalOperator . ' ', $conditions);
        if ($maxResults > 0) {
            $sql .= ' LIMIT ' . $maxResults;
        }

        try {
            $r = $this->dbConn->query($sql);
        } catch (PDOException $e) {
            if ( $e->getCode() === '42000' || $e->getCode() === '42S22') {
                // The exception was thrown because of an SQL syntax error but
                // this should only happen when one of the keys does not exist
                // or is of the wrong type. This just means that the search
                // did not have any results, so let's set the error code
                // to be 'empty result set'
                // However, just in case this may be hiding something else,
                // let's log it as info
                // TODO: add an optional full table schema check in order avoid ambiguities here
                $this->logger->info('Query error in realFindRows (reported as no results)',
                    ['query' => $sql, 'message' => $e->getMessage(), 'code' => $e->getCode()]);
                return [];
            }
            // @codeCoverageIgnoreStart
            $this->setError('Query error in realFindRows: code  ' . $e->getCode() . ' : '
                . $e->getMessage() . ' :: query = ' . $sql, self::ERROR_MYSQL_QUERY_ERROR);
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
            // @codeCoverageIgnoreEnd
        }

        if ($r === false) {
            // @codeCoverageIgnoreStart
            $this->setError('Unknown error in realFindRows '
                . 'when executing query: ' . $sql, self::ERROR_UNKNOWN_ERROR);
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
            // @codeCoverageIgnoreEnd
        }
        return $this->forceIntIds($r->fetchAll(PDO::FETCH_ASSOC));
    }

    private function  getSqlConditionFromSpec(array $spec) : string {
        $column = $spec['column'];
        $quotedValue = $this->quoteValue($spec['value']);

        switch ($spec['condition']) {
            case self::COND_EQUAL_TO:
                return "`$column`=" . $quotedValue;

            case self::COND_NOT_EQUAL_TO:
                return "`$column`!=" . $quotedValue;

            case self::COND_LESS_THAN:
                return "`$column`<" . $quotedValue;

            case self::COND_LESS_OR_EQUAL_TO:
                return "`$column`<=" . $quotedValue;

            case self::COND_GREATER_THAN:
                return "`$column`>" . $quotedValue;

            case self::COND_GREATER_OR_EQUAL_TO:
                return "`$column`>=" . $quotedValue;
        }
        // @codeCoverageIgnoreStart
        // This should never happen, if it does there's programming mistake!
        $this->setError(__METHOD__  . ' got into an invalid state, line ' . __LINE__, self::ERROR_UNKNOWN_ERROR);
        throw new LogicException($this->getErrorMessage(), $this->getErrorCode());
        // @codeCoverageIgnoreEnd
    }
}
