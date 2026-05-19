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

use Iterator;
use LogicException;
use Override;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\DataTable\Exception\InvalidWhereClauseException;
use ThomasInstitut\DataTable\Exception\LastInsertIdNotAvailableException;
use ThomasInstitut\DataTable\Exception\RowAlreadyExists;
use ThomasInstitut\DataTable\Exception\RowDoesNotExist;
use ThomasInstitut\DataTable\IdGenerator\IdGenerator;
use ThomasInstitut\DataTable\PdoProvider\PdoProvider;
use ThomasInstitut\DataTable\PdoProvider\SimplePdoProvider;
use ThomasInstitut\DataTable\ResultsIterator\ArrayResultsIterator;
use ThomasInstitut\DataTable\ResultsIterator\PdoResultsIterator;
use ThomasInstitut\DataTable\ResultsIterator\ResultsIterator;
use ThomasInstitut\DataTable\SqlDialect\SqlDialect;


/**
 * Implements a data table with SQL databases through PDO.
 */
class PdoDataTable extends GenericDataTable
{

    // CONSTANTS

    public const int ERROR_MYSQL_QUERY_ERROR = 1010;
    public const int ERROR_REQUIRED_COLUMN_NOT_FOUND = 1020;
    public const int ERROR_WRONG_COLUMN_TYPE = 1030;
    public const int ERROR_TABLE_NOT_FOUND = 1040;
    public const int ERROR_PREPARING_STATEMENTS = 1070;
    public const int ERROR_EXECUTING_STATEMENT = 1080;
    public const int ERROR_INVALID_WHERE_CLAUSE = 1090;

    public const int ERROR_TABLE_ALREADY_IN_TRANSACTION = 1100;
    public const int ERROR_MYSQL_ALREADY_IN_TRANSACTION = 1101;
    public const int ERROR_MYSQL_COULD_NOT_BEGIN_TRANSACTION = 1102;
    public const int ERROR_TABLE_NOT_IN_TRANSACTION = 1103;
    public const int ERROR_MYSQL_COULD_NOT_COMMIT = 1104;
    public const int ERROR_MYSQL_COULD_NOT_ROLLBACK = 1105;

    protected PdoProvider $pdoProvider;

    /**
     * @var PDOStatement[]
     */
    protected array $statements;
    private bool $inTransaction;

    /**
     *
     * @param PDO|PdoProvider $pdoOrProvider initialized PDO connection or provider
     * @param string $tableName SQL table name
     */
    public function __construct(PDO|PdoProvider $pdoOrProvider, string $tableName, protected SqlDialect $sqlDialect, private readonly bool $useDbAutoInc = false, string $idColumnName = self::DEFAULT_ID_COLUMN_NAME)
    {
        parent::__construct();

        $this->tableName = $tableName;
        $this->idColumnName = $idColumnName;
        if ($pdoOrProvider instanceof PDO) {
            $this->pdoProvider = new SimplePdoProvider($pdoOrProvider);
        } else {
            $this->pdoProvider = $pdoOrProvider;
        }
        $this->inTransaction = false;

        if (!$this->isTableColumnValid($this->idColumnName, ['int', 'bigint'])) {
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
        }

        $this->pdoProvider->getPdo()->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $quotedTableName = $this->sqlDialect->quoteIdentifier($this->tableName);
        $quotedIdColumnName = $this->sqlDialect->quoteIdentifier($this->idColumnName);

        // Pre-prepare common statements
        try {
            $this->statements['rowExistsById'] =
                $this->pdoProvider->getPdo()->prepare('SELECT ' . $quotedIdColumnName . ' FROM ' . $quotedTableName
                    . ' WHERE ' . $quotedIdColumnName . '= :id');
            $this->statements['deleteRow'] =
                $this->pdoProvider->getPdo()->prepare('DELETE FROM ' . $quotedTableName
                    . ' WHERE ' . $quotedIdColumnName . '= :id');
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
     * @throws InvalidArgumentException
     */
    #[Override]
    public function setIdGenerator(IdGenerator $ig): void
    {
        if ($this->useDbAutoInc) {
            throw new InvalidArgumentException('Cannot set custom ID generator when using database auto-increment');
        }
        parent::setIdGenerator($ig);
    }


    /**
     * Checks a table column.
     *
     * Returns false if the column or table does not exist or if its type is not
     * one in the given list.
     *
     * @param string $columnName
     * @param string[] $requiredTypes
     * @return bool
     */
    protected function isTableColumnValid(string $columnName, array $requiredTypes): bool
    {
        try {
            $r = $this->pdoProvider->getPdo()->query(
                $this->sqlDialect->getTableColumnInfoQuery($this->tableName, $columnName)
            );
        } catch (PDOException $e) { // @codeCoverageIgnore
            if ($this->sqlDialect->isTableNotFoundException($e)) {
                $this->setError('Table ' . $this->tableName . ' not found',
                    self::ERROR_TABLE_NOT_FOUND);
                return false;
            }
            // @codeCoverageIgnoreStart
            $dialectName = $this->sqlDialect->getName();
            $this->setError('Query error checking ' . $dialectName . ' column '
                . $this->tableName . '::' . $columnName . ' : ' . $dialectName . ' Error Code ' . $e->getCode() . ", msg = " . $e->getMessage(),
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
            $this->setError('Required column ' . $columnName . ' not found in table ' . $this->tableName,
                self::ERROR_REQUIRED_COLUMN_NOT_FOUND);
            return false;
        }

        $columnInfo = $r->fetch(PDO::FETCH_ASSOC);
        if (!is_array($columnInfo)) {
            $this->setError('Required column ' . $columnName . ' not found in table ' . $this->tableName,
                self::ERROR_REQUIRED_COLUMN_NOT_FOUND);
            return false;
        }

        $columnType = $this->sqlDialect->getColumnType($columnInfo);
        $columnHasGoodType = false;
        foreach ($requiredTypes as $requiredType) {
            if ($this->sqlDialect->matchesRequiredType($columnType, $requiredType)) {
                $columnHasGoodType = true;
                break;
            }
        }
        if (!$columnHasGoodType) {
            $dialectName = $this->sqlDialect->getName();
            $this->setError('Wrong ' . $dialectName . ' column type for  '
                . $this->tableName . '::' . $columnName
                . ', required=\'' . implode(' or ', $requiredTypes)
                . '\', actual=\'' . $columnType . '\'',
                self::ERROR_WRONG_COLUMN_TYPE);
            return false;
        }
        return true;
    }

    /**
     * @throws RuntimeException
     */
    public function rowExists(int $rowId): bool
    {
        $this->resetError();
        $this->executeStatement('rowExistsById', ['id' => $rowId]);
        if ($this->statements['rowExistsById']->rowCount() !== 1) {
            return false;
        }
        return true;
    }

    /**
     * @param array $theRow
     * @return int
     * @throws RowAlreadyExists
     * @throws RuntimeException
     * @throws LastInsertIdNotAvailableException
     */
    #[Override]
    public function createRow(array $theRow): int
    {
        if (!$this->useDbAutoInc) {
            // use regular id creator
            return parent::createRow($theRow);
        }
        if (!isset($theRow[$this->idColumnName]) || !is_int($theRow[$this->idColumnName])) {
            $theRow[$this->idColumnName] = 0;
        }
        if ($theRow[$this->idColumnName] !== 0) {
            if ($this->rowExists($theRow[$this->idColumnName])) {
                $this->setError('The row with given id (' . $theRow[$this->idColumnName] . ') already exists, cannot create',
                    self::ERROR_ROW_ALREADY_EXISTS);
                throw new RowAlreadyExists($this->getErrorMessage(), $this->getErrorCode());
            }
        }
        $this->doQuery($this->getInsertQuery($theRow), 'createRow');
        $lastInsertId = $this->pdoProvider->getPdo()->lastInsertId();
        if ($lastInsertId === false) { // @codeCoverageIgnore
            // if this happens, it means that the database does not support lastInsertId(), which is deal-breaker for this class
            throw new LastInsertIdNotAvailableException('Failed to retrieve last insert ID after creating row'); // @codeCoverageIgnore
        }
        return intval($lastInsertId);
    }

    protected function getInsertQuery(array $theRow): string
    {
        $keys = array_keys($theRow);
        $quotedKeys = [];
        foreach ($keys as $key) {
            $quotedKeys[] = $this->sqlDialect->quoteIdentifier($key);
        }
        $sql = 'INSERT INTO ' . $this->sqlDialect->quoteIdentifier($this->tableName) . ' (' .
            implode(',', $quotedKeys) . ') VALUES ';
        $values = [];
        foreach ($keys as $key) {
            $values[] = $this->quoteValue($theRow[$key]);
        }
        $sql .= '(' . implode(',', $values) . ');';
        return $sql;
    }

    public function realCreateRow(array $theRow): int
    {
        $this->doQuery($this->getInsertQuery($theRow), 'realCreateRow');
        return (int)$theRow[$this->idColumnName];
    }


    public function realUpdateRow(array $theRow): void
    {

        if (!$this->rowExists($theRow[$this->idColumnName])) {
            $this->setError('Id ' . $theRow[$this->idColumnName] . ' does not exist, cannot update',
                self::ERROR_ROW_DOES_NOT_EXIST);
            throw new RowDoesNotExist($this->getErrorMessage(), $this->getErrorCode());
        }
        $keys = array_keys($theRow);
        $sets = [];
        foreach ($keys as $key) {
            if ($key === $this->idColumnName) {
                continue;
            }
            $sets[] = $this->sqlDialect->quoteIdentifier($key) . '=' . $this->quoteValue($theRow[$key]);
        }
        $sql = 'UPDATE ' . $this->sqlDialect->quoteIdentifier($this->tableName) . ' SET '
            . implode(',', $sets) . ' WHERE ' . $this->sqlDialect->quoteIdentifier($this->idColumnName) . '=' . $theRow[$this->idColumnName];

        $this->doQuery($sql, 'realUpdateRow');
    }

    /**
     * Returns a string with a correctly quoted value for use in MySQL
     *
     * @param $var
     * @return string
     */
    public function quoteValue($var): string
    {
        if (is_string($var)) {
            return $this->pdoProvider->getPdo()->quote($var);
        }
        if (is_null($var)) {
            return 'NULL';
        }
        return (string)$var;
    }

    public function getAllRows(): ResultsIterator
    {
        $sql = 'SELECT * FROM ' . $this->tableName;
        return new PdoResultsIterator($this->doQuery($sql, 'getAllRows'), $this->idColumnName);

    }

    /**
     * Executes a general SELECT query on the data table
     *
     * Try not to use this method directly, prefer to use search() or findRows() since
     * search is implemented by any DataTable not just by PdoDataTable
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
     * @throws InvalidWhereClauseException
     * @see DataTable::search()  Preferred alternative
     * @see DataTable::findRows() Preferred alternative
     */
    public function select(string $what, string $where, int $limit, string $orderBy, string $context): PDOStatement
    {

        if ($what === '') {
            $what = '*';
        }
        if ($where === '') {
            $this->setError('Empty where clause', self::ERROR_INVALID_WHERE_CLAUSE);
            throw new InvalidWhereClauseException($this->getErrorMessage(), $this->getErrorCode());
        }

        $sql = 'SELECT ' . $what . ' FROM ' . $this->sqlDialect->quoteIdentifier($this->tableName) . ' WHERE ' . $where;
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }
        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        return $this->doQuery($sql, $context);
    }


    public function getRow(int $rowId): ?array
    {
        $rows = $this->findRows([$this->idColumnName => $rowId]);
        if ($rows->count() === 0) {
            $this->setError("Row $rowId does not exist", self::ERROR_ROW_DOES_NOT_EXIST);
            return null;
        }
        return $rows->getFirst();
    }

    /**
     * @throws RuntimeException
     */
    public function getMaxValueInColumn(string $columnName): int
    {
        $sql = 'SELECT MAX(' . $columnName . ') FROM ' . $this->tableName;

        $r = $this->doQuery($sql, 'getMaxValueInColumn');

        $maxId = $r->fetchColumn();
        if ($maxId === null) {
            return 0;
        }
        return (int)$maxId;
    }

    public function getMaxId(): int
    {
        return $this->getMaxValueInColumn($this->idColumnName);

    }

    public function getIdForKeyValue(string $key, mixed $value): int
    {
        $rows = $this->findRows([$key => $value], 1);
        if ($rows->count() === 0) {
            $this->setError('Value ' . $value . ' for key ' . $key . 'not found', self::ERROR_KEY_VALUE_NOT_FOUND);
            return self::NULL_ROW_ID;
        }
        return $rows->getFirst()[$this->idColumnName];
    }

    public function deleteRow(int $rowId): int
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
                $rows[$i][$this->idColumnName] = (int)$rows[$i][$this->idColumnName];
            }
        }
        return $rows;
    }

    protected function doQuery(string $sql, string $context): PDOStatement
    {
        try {
            $r = $this->pdoProvider->getPdo()->query($sql);
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

    #[Override]
    public function getUniqueIds(): Iterator
    {
        $tableName = $this->tableName;
        $idColumn = $this->idColumnName;
        $quotedTableName = $this->sqlDialect->quoteIdentifier($tableName);
        $quotedIdColumn = $this->sqlDialect->quoteIdentifier($idColumn);
        $result = $this->doQuery("SELECT DISTINCT($quotedIdColumn) FROM $quotedTableName ORDER BY $quotedTableName.$quotedIdColumn", "getUniqueIds");
        return new PdoUniqueIdsIterator($result);
    }


    /**
     * Executes a named prepared statement,
     * if there's any problem, throws a Runtime exception
     *
     * @param string $statement
     * @param array $param
     */
    protected function executeStatement(string $statement, array $param): void
    {
        try {
            $result = $this->statements[$statement]->execute($param);
        } catch (PDOException $e) {
            $this->setError($this->sqlDialect->getName() . ' error when executing '
                . 'prepared statement "' . $statement . '": '
                . $e->getMessage(), self::ERROR_MYSQL_QUERY_ERROR);
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
        }

        if ($result === false) {
            // @codeCoverageIgnoreStart
            $this->setError('Unknown error when executing ' .
                'prepared statement "' . $statement . '"', self::ERROR_EXECUTING_STATEMENT);
            throw new RuntimeException($this->getErrorMessage(), $this->getErrorCode());
            // @codeCoverageIgnoreEnd
        }
    }


    /**
     * Returns the sql query needed to get the search results
     *
     * @param array $searchSpecArray
     * @param int $searchType
     * @param int $maxResults
     * @return string
     */
    protected function getSearchSqlQuery(array $searchSpecArray, int $searchType, int $maxResults): string
    {

        $conditions = [];
        foreach ($searchSpecArray as $spec) {
            $conditions[] = $this->getSqlConditionFromSpec($spec);
        }

        $sqlLogicalOperator = 'AND';
        if ($searchType == self::SEARCH_OR) {
            $sqlLogicalOperator = 'OR';
        }
        $sql = 'SELECT * FROM ' . $this->sqlDialect->quoteIdentifier($this->tableName) . ' WHERE '
            . implode(' ' . $sqlLogicalOperator . ' ', $conditions);
        if ($maxResults > 0) {
            $sql .= ' LIMIT ' . $maxResults;
        }

        return $sql;
    }

    /**
     * @inheritdoc
     */
    public function search(array $searchSpecArray, int $searchType = self::SEARCH_AND, int $maxResults = 0): ResultsIterator
    {
        $this->checkSpec($searchSpecArray, $searchType);


        $sql = $this->getSearchSqlQuery($searchSpecArray, $searchType, $maxResults);

        try {
            $r = $this->pdoProvider->getPdo()->query($sql);
        } catch (PDOException $e) {
            if ($this->sqlDialect->isSearchErrorRecoverable($e)) {
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
                return new ArrayResultsIterator([]);
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
        return new PdoResultsIterator($r, $this->idColumnName);
    }

    protected function getSqlConditionFromSpec(array $spec): string
    {
        $column = $spec['column'];
        $quotedColumn = $this->sqlDialect->quoteIdentifier($column);
        $quotedValue = $this->quoteValue($spec['value']);

        switch ($spec['condition']) {
            case self::COND_EQUAL_TO:
                return $quotedColumn . '=' . $quotedValue;

            case self::COND_NOT_EQUAL_TO:
                return $quotedColumn . '!=' . $quotedValue;

            case self::COND_LESS_THAN:
                return $quotedColumn . '<' . $quotedValue;

            case self::COND_LESS_OR_EQUAL_TO:
                return $quotedColumn . '<=' . $quotedValue;

            case self::COND_GREATER_THAN:
                return $quotedColumn . '>' . $quotedValue;

            case self::COND_GREATER_OR_EQUAL_TO:
                return $quotedColumn . '>=' . $quotedValue;
        }
        // @codeCoverageIgnoreStart
        // This should never happen, if it does, there's programming mistake!
        $this->setError(__METHOD__ . ' got into an invalid state, line ' . __LINE__, self::ERROR_UNKNOWN_ERROR);
        throw new LogicException($this->getErrorMessage(), $this->getErrorCode());
        // @codeCoverageIgnoreEnd
    }

    /**
     * Returns true if db table supports transactions.
     *
     * @return bool
     */
    #[Override]
    public function supportsTransactions(): bool
    {
        $r = $this->doQuery($this->sqlDialect->getTableStatusQuery($this->tableName), __FUNCTION__);
        $info = $r->fetch(PDO::FETCH_ASSOC);
        if (!is_array($info)) {
            return false;
        }
        return $this->sqlDialect->tableSupportsTransactions($info);
    }

    /**
     * Starts a db transaction.
     *
     * Care must be taken when using transactions in the context of DataTable operations.
     * In particular, a db transaction applies to the database connection as a whole, so starting
     * a transaction in a PdoDataTable will also start a transaction in all other PdoDataTables that
     * share the same database connection. This might be desirable, but it can also be dangerous.
     *
     * Returns true if a transaction started without problems.
     *
     * Returns false if the underlying database is in a transaction already or if it could not start the transaction.
     * The actual error can be retrieved with getErrorCode and getErrorMessage
     *
     * @return bool
     */

    #[Override]
    public function startTransaction(): bool
    {
        $this->resetError();
        if ($this->inTransaction) {
            $this->setError("Current table already in a transaction", self::ERROR_TABLE_ALREADY_IN_TRANSACTION);
            return false;
        }
        if ($this->pdoProvider->getPdo()->inTransaction()) {
            $this->setError("Current table already in a transaction", self::ERROR_MYSQL_ALREADY_IN_TRANSACTION);
            return false;
        }
        $this->inTransaction = $this->pdoProvider->getPdo()->beginTransaction();
        if (!$this->inTransaction) {
            $this->setError($this->sqlDialect->getName() . " could not begin transaction", self::ERROR_MYSQL_COULD_NOT_BEGIN_TRANSACTION);
            return false;
        }
        return $this->inTransaction;
    }

    /**
     * Commits an already started transaction.
     *
     * Will only commit if the transaction was started in this DataTable.
     *
     * Returns false if the PdoDataTable is not in a transaction or if the underlying database could not execute the commit.
     * The actual error can be retrieved with getErrorCode and getErrorMessage
     *
     * @return bool
     */
    #[Override]
    public function commit(): bool
    {
        $this->resetError();
        if (!$this->inTransaction) {
            $this->setError("Table not in a transaction, commit not possible", self::ERROR_TABLE_NOT_IN_TRANSACTION);
            return false;
        }
        if ($this->pdoProvider->getPdo()->commit()) {
            $this->inTransaction = false;
            return true;
        }
        $this->inTransaction = $this->isUnderlyingDatabaseInTransaction();
        $msg = $this->inTransaction ? "table still in a transaction" : "transaction ended";
        $this->setError($this->sqlDialect->getName() . " could not commit, $msg", self::ERROR_MYSQL_COULD_NOT_COMMIT);
        return false;
    }

    #[Override]
    public function rollBack(): bool
    {
        $this->resetError();
        if (!$this->inTransaction) {
            $this->setError("Table not in a transaction, rollBack not possible", self::ERROR_TABLE_NOT_IN_TRANSACTION);
            return false;
        }
        if ($this->pdoProvider->getPdo()->rollBack()) {
            $this->inTransaction = false;
            return true;
        }
        $this->inTransaction = $this->isUnderlyingDatabaseInTransaction();
        $msg = $this->inTransaction ? "table still in a transaction" : "transaction ended";
        $this->setError($this->sqlDialect->getName() . " could not roll back, $msg", self::ERROR_MYSQL_COULD_NOT_ROLLBACK);
        return false;
    }

    #[Override]
    public function isInTransaction(): bool
    {
        return $this->inTransaction;
    }

    #[Override]
    public function isUnderlyingDatabaseInTransaction(): bool
    {
        return $this->pdoProvider->getPdo()->inTransaction();
    }
}
