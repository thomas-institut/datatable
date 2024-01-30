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

use ArrayIterator;
use Exception;
use Iterator;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Traversable;


abstract class GenericDataTable implements DataTable
{
    /**
     *
     * @var string
     */
    protected string $tableName;

    protected string $idColumnName;

    /** *********************************************************************
     * PUBLIC METHODS
     ************************************************************************/
    
    /**
     * Constructor
     */
    public function __construct(IdGenerator $idGenerator = null) {
        if ($idGenerator === null) {
            $this->setIdGenerator(new SequentialIdGenerator());
        } else {
            $this->setIdGenerator($idGenerator);
        }
        $this->resetError();
        $this->setLogger(new NullLogger());
        $this->idColumnName = self::DEFAULT_ID_COLUMN_NAME;
    }

    public function setIdColumnName(string $columnName) : void {
        $this->idColumnName = $columnName;
    }

    public function getIdColumnName() : string {
        return $this->idColumnName;
    }

    public function getName(): string
    {
        return $this->tableName;
    }

    public function setName(string $name) : void
    {
        $this->tableName = $name;
    }

    public function setIdGenerator(IdGenerator $ig) : void {
        $this->idGenerator = $ig;
    }

    public function setLogger(LoggerInterface $logger):void
    {
        $this->logger = $logger;
    }


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

    public function getIterator(): Traversable
    {
        return $this->getAllRows();
    }

    public function getUniqueIds(): Iterator
    {
        $rowIterator = $this->getAllRows();
        $allIds = [];
        foreach($rowIterator as $row) {
            $allIds[] = $row[$this->idColumnName];
        }
        $ids = array_unique($allIds, SORT_NUMERIC);
        sort($ids, SORT_NUMERIC);
        return new ArrayIterator($ids);
    }

    abstract public function rowExists(int $rowId) : bool;

    public function createRow(array $theRow) : int
    {
        $this->resetError();
        $preparedRow = $this->getRowWithGoodIdForCreation($theRow);
        return $this->realCreateRow($preparedRow);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->rowExists(intval($offset));
    }


    public function offsetGet(mixed $offset): ?array
    {
        return $this->getRow(intval($offset));
    }


    /**
     *
     * @throws InvalidRowForUpdate
     * @throws RowAlreadyExists
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->createRow($value);
            return;
        }
        $id = intval($offset);
        $value[$this->idColumnName] = $id;
        if ($this->rowExists($id)) {
            try {
                $this->updateRow($value);
            } catch(RowDoesNotExist) {
                // this should never happen
            }
        } else {
            try {
                $this->createRow($value);
            } catch (RowAlreadyExists) {
                // this should never happen
            }

        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->deleteRow(intval($offset));
    }

    public function supportsTransactions(): bool
    {
        return false;
    }

    public function startTransaction(): bool
    {
        $this->setError("Transactions not supported", self::ERROR_TRANSACTIONS_NOT_SUPPORTED);
        return false;
    }

    public function isInTransaction(): bool
    {
        $this->setError("Transactions not supported", self::ERROR_TRANSACTIONS_NOT_SUPPORTED);
        return false;
    }

    public function isUnderlyingDatabaseInTransaction() : bool
    {
        $this->setError("Transactions not supported", self::ERROR_TRANSACTIONS_NOT_SUPPORTED);
        return false;
    }

    public function commit(): bool
    {
        $this->setError("Transactions not supported", self::ERROR_TRANSACTIONS_NOT_SUPPORTED);
        return false;
    }

    public function rollBack(): bool
    {
        $this->setError("Transactions not supported", self::ERROR_TRANSACTIONS_NOT_SUPPORTED);
        return false;
    }

    abstract public function getRow(int $rowId) : ?array;

    abstract public function getAllRows() : DataTableResultsIterator;

    abstract public function deleteRow(int $rowId) : int;

    /**
     * @inheritdoc
     */
    public function findRows(array $rowToMatch, int $maxResults = 0) : DataTableResultsIterator
    {
        $searchSpec = [];

        $givenRowKeys = array_keys($rowToMatch);
        foreach ($givenRowKeys as $key) {
            $searchSpec[] = [
                self::SEARCH_SPEC_COLUMN => $key,
                self::SEARCH_SPEC_CONDITION => self::COND_EQUAL_TO,
                self::SEARCH_SPEC_VALUE => $rowToMatch[$key]
            ];
        }
        try {
            $results = $this->search($searchSpec, self::SEARCH_AND, $maxResults);
        } catch (InvalidSearchSpec|InvalidSearchType ) {
            // this should never happen!
        }
        return $results ?? new DataTableResultsArrayIterator([]);
    }

    /**
     * @inheritdoc
     * @throws InvalidSearchSpec
     * @throws InvalidSearchType
     */
    abstract public function search(array $searchSpecArray, int $searchType = self::SEARCH_AND, int $maxResults = 0) : DataTableResultsIterator;


    /**
     * @throws InvalidRowForUpdate
     * @throws RowDoesNotExist
     */
    public function updateRow(array $theRow) : void
    {
        $this->resetError();
        if (!$this->isRowIdGoodForRowUpdate($theRow, 'DataTable updateRow')) {
            throw new InvalidRowForUpdate($this->getErrorMessage(), $this->getErrorCode());
        }
        $this->realUpdateRow($theRow);
    }


    /**
     * @inheritdoc
     * @deprecated Use search functions
     */

    abstract public function getIdForKeyValue(string $key, mixed $value) : int;


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
     * Updates the given row, which must have a valid ID.
     * If there is not a row with that id, throws an InvalidArgument exception.
     *
     * Must throw a Runtime Exception if the row was not updated
     *
     * @param array $theRow
     * @return void
     * @throws RowDoesNotExist
     */
    abstract protected function realUpdateRow(array $theRow) : void;


    /**
     *
     * PROTECTED METHODS
     *
     */

    /**
     * Returns theRow with a valid ID for creation: if there's no id
     * in the given row, the given ID is negative or 0, or not an integer, the id is set to
     * an unused ID
     *
     * @param array $theRow
     * @return array
     * @throws RowAlreadyExists
     */
    protected function getRowWithGoodIdForCreation(array $theRow) : array
    {
        if (!isset($theRow[$this->idColumnName]) || !is_int($theRow[$this->idColumnName]) || $theRow[$this->idColumnName]<=0) {
            $theRow[$this->idColumnName] = $this->getOneUnusedId();
        } else {
            if ($this->rowExists($theRow[$this->idColumnName])) {
                $this->setError('The row with given id ('. $theRow[$this->idColumnName] . ') already exists, cannot create',
                    self::ERROR_ROW_ALREADY_EXISTS);
                throw new RowAlreadyExists($this->getErrorMessage(), $this->getErrorCode());
            }
        }
        return $theRow;
    }
    
     /**
      * Returns a unique ID that does not exist in the table,
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
     * @throws InvalidSearchType
     * @throws InvalidSearchSpec
     */
    protected function checkSpec(array $searchSpecArray, int $searchType) : void {
        $this->resetError();
        $searchSpecCheck = $this->checkSearchSpecArrayValidity($searchSpecArray);
        if ($searchSpecCheck !== []) {
            $this->setError('searchSpec is not valid', self::ERROR_INVALID_SPEC_ARRAY, $searchSpecCheck);
            throw new InvalidSearchSpec($this->getErrorMessage(), $this->getErrorCode());
        }
        if ($searchType !== self::SEARCH_AND && $searchType !== self::SEARCH_OR) {
            $this->setError('Invalid search type', self::ERROR_INVALID_SEARCH_TYPE);
            throw new InvalidSearchType($this->getErrorMessage(), $this->getErrorCode());
        }
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

    protected function log(string $logLevel, string $msg, int $code, array $otherContext): void
    {
        $this->logger->log($logLevel, $msg, array_merge([ 'code' => $code], $otherContext));
    }

    protected function logWarning(string $msg, int $code, array $otherContext = []): void
    {
        $this->log(LogLevel::WARNING, $msg, $code, $otherContext);
    }

    /**********************************************************************
     * PRIVATE AREA
     ************************************************************************/

    /**
     * @var IdGenerator
     */
    private IdGenerator $idGenerator;


    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     *
     * @var string
     */
    private string $errorMessage;

    /**
     *
     * @var int
     */
    private int $errorCode;

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
        if (!isset($theRow[$this->idColumnName]))  {
            $this->setError('Id not set in given row' . " ($context)", self::ERROR_ID_NOT_SET);
            return false;
        }

        if ($theRow[$this->idColumnName] <= 0) {
            $this->setError('Id is equal to zero in given row' . " ($context)", self::ERROR_ID_IS_ZERO);
            return false;
        }
        if (!is_int($theRow[$this->idColumnName])) {
            $this->setError('Id in given row is not an integer' . " ($context)", self::ERROR_ID_NOT_INTEGER);
            return false;
        }
        return true;
    }

}
