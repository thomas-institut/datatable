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

namespace ThomasInstitut\DataTable;

use LogicException;

class InMemoryDataTable extends GenericDataTable
{
    
    private array $theData;

    public function __construct(array &$data = null, IdGenerator $idGenerator = null)
    {
        parent::__construct($idGenerator);
        if ($data === null) {
            $this->theData = [];
        } else {
            $this->theData = &$data;
        }
    }


    public function getAllRows() : DataTableResultsIterator
    {
        return new DataTableResultsArrayIterator($this->theData);
    }
    
    public function rowExists(int $rowId) : bool
    {
        $this->resetError();
        if (isset($this->theData[$rowId])) {
            return true;
        }
        return false;
    }
    
    public function realCreateRow(array $theRow) : int
    {
        $this->theData[$theRow[$this->idColumnName]] = [];
        $this->theData[$theRow[$this->idColumnName]] = $theRow;
        return $theRow[$this->idColumnName];
    }
    
    public function deleteRow(int $rowId) : int
    {
        if (!$this->rowExists($rowId)) {
            return 0;
        }
        unset($this->theData[$rowId]);
        return 1;
    }
    
    public function realUpdateRow(array $theRow) : void
    {
        if (!$this->rowExists($theRow[$this->idColumnName])) {
            $this->setError('Id ' . $theRow[$this->idColumnName] . ' does not exist, cannot update', self::ERROR_ROW_DOES_NOT_EXIST);
            throw new RowDoesNotExist($this->getErrorMessage(), $this->getErrorCode());
        }
        $keys = array_keys($theRow);
        $rowId = $theRow[$this->idColumnName];
        
        foreach ($keys as $k) {
            $this->theData[$rowId][$k] = $theRow[$k];
        }
    }


    public function getMaxValueInColumn(string $columnName): int
    {
        if (count($this->theData) !== 0) {
            return max(array_column($this->theData, $columnName));
        } else {
            return 0;
        }
    }

    public function getMaxId() : int
    {
        return $this->getMaxValueInColumn($this->idColumnName);
    }

    public function getRow(int $rowId) : ?array
    {
        if ($this->rowExists($rowId)) {
            return $this->theData[$rowId];
        }
        $msg = 'Row ' . $rowId . ' does not exist';
        $errorCode = self::ERROR_ROW_DOES_NOT_EXIST;
        $this->setError($msg, $errorCode, [ 'method' => __METHOD__]);
        return null;
    }
    
    public function getIdForKeyValue(string $key, mixed $value) : int
    {
        $id = array_search(
            $value,
            array_column($this->theData, $key, $this->idColumnName),
            true
        );
        if ($id === false) {
            $this->logWarning('Value ' . $value . ' for key \'' . $key .  '\' not found', self::ERROR_KEY_VALUE_NOT_FOUND);
            return self::NULL_ROW_ID;
        }
        return $id;
    }

    /**
     * @inheritdoc
     * @throws InvalidSearchSpec
     * @throws InvalidSearchType
     */
    public function search(array $searchSpecArray, int $searchType = self::SEARCH_AND, int $maxResults = 0): DataTableResultsIterator
    {
        $this->checkSpec($searchSpecArray, $searchType);

        $results = [];
        foreach ($this->theData as $dataRow) {
            if ($this->matchSearchSpec($dataRow, $searchSpecArray, $searchType)) {
                $results[] = $dataRow;
                if ($maxResults > 0 && count($results) === $maxResults) {
                    return new DataTableResultsArrayIterator($results);
                }
            }
        }
        return new DataTableResultsArrayIterator($results);
    }

    /**
     * Returns true is the given $dataRow matches the given $searchSpec and $searchType
     *
     * @param array $dataRow
     * @param array $searchSpec
     * @param int $searchType
     * @return bool
     */
    private function matchSearchSpec(array $dataRow, array $searchSpec, int $searchType) : bool {

        switch($searchType) {
            case self::SEARCH_AND:
                foreach ($searchSpec as $spec) {
                    if (!$this->match($dataRow, $spec)) {
                        return false;
                    }
                }
                return true;

            case self::SEARCH_OR:
                foreach ($searchSpec as $spec) {
                    if ($this->match($dataRow, $spec)) {
                        return true;
                    }
                }
                return false;
        }
        // @codeCoverageIgnoreStart
        // This should never happen, if it does there's a programming mistake!
        $this->setError(__METHOD__  . ' got into an invalid state, line ' . __LINE__, self::ERROR_UNKNOWN_ERROR);
        throw new LogicException($this->getErrorMessage(), $this->getErrorCode());
        // @codeCoverageIgnoreEnd

    }

    private function match(array $dataRow, array $spec) : bool {
        $column = $spec['column'];
        $value = $spec['value'];

        if (is_string($value)) {
            switch ($spec['condition']) {
                case self::COND_EQUAL_TO:
                    return strcmp($dataRow[$column], $value) === 0;

                case self::COND_NOT_EQUAL_TO:
                    return strcmp($dataRow[$column], $value) !== 0;

                case self::COND_LESS_THAN:
                    return strcmp($dataRow[$column], $value) < 0;

                case self::COND_LESS_OR_EQUAL_TO:
                    return strcmp($dataRow[$column], $value) <= 0;

                case self::COND_GREATER_THAN:
                    return strcmp($dataRow[$column], $value) > 0;

                case self::COND_GREATER_OR_EQUAL_TO:
                    return strcmp($dataRow[$column], $value) >= 0;
            }
        } else {
            switch ($spec['condition']) {
                case self::COND_EQUAL_TO:
                    return $dataRow[$column] === $value;

                case self::COND_NOT_EQUAL_TO:
                    return $dataRow[$column] !== $value;

                case self::COND_LESS_THAN:
                    return $dataRow[$column] < $value;

                case self::COND_LESS_OR_EQUAL_TO:
                    return $dataRow[$column] <= $value;

                case self::COND_GREATER_THAN:
                    return $dataRow[$column] > $value;

                case self::COND_GREATER_OR_EQUAL_TO:
                    return $dataRow[$column] >= $value;
            }
        }
        // @codeCoverageIgnoreStart
        // This should never happen, if it does there's a programming mistake!
        $this->setError(__METHOD__  . ' got into an invalid state, line ' . __LINE__, self::ERROR_UNKNOWN_ERROR);
        throw new LogicException($this->getErrorMessage(), $this->getErrorCode());
        // @codeCoverageIgnoreEnd
    }

}
