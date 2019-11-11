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

class InMemoryDataTable extends DataTable
{
    
    private $theData = [];
    
    public function getAllRows() : array
    {
        return $this->theData;
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
        $this->theData[$theRow['id']] = [];
        $this->theData[$theRow['id']] = $theRow;
        return $theRow['id'];
    }
    
    public function deleteRow(int $rowId) : bool
    {
        if (!$this->rowExists($rowId)) {
            return false;
        }
        unset($this->theData[$rowId]);
        return true;
    }
    
    public function realUpdateRow(array $theRow) : void
    {
        if (!$this->rowExists($theRow['id'])) {
            $this->setErrorCode(self::ERROR_ROW_DOES_NOT_EXIST);
            $this->setErrorMessage('Id ' . $theRow['id'] . ' does not exist, cannot update');
            throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
        }
        $keys = array_keys($theRow);
        $rowId = $theRow['id'];
        
        foreach ($keys as $k) {
            $this->theData[$rowId][$k] = $theRow[$k];
        }
    }
    
    public function getMaxId() : int
    {
        if (count($this->theData) !== 0) {
            return max(array_column($this->theData, 'id'));
        } else {
            return 0;
        }
    }
    
    public function getRow(int $rowId) : array
    {
        if ($this->rowExists($rowId)) {
            return $this->theData[$rowId];
        }
        $msg = 'Row ' . $rowId . ' does not exist';
        $errorCode = self::ERROR_ROW_DOES_NOT_EXIST;
        $this->setError($msg, $errorCode);
        throw new InvalidArgumentException($msg, $errorCode);
    }
    
    public function getIdForKeyValue(string $key, $value) : int
    {
        $id = array_search(
            $value,
            array_column($this->theData, $key, 'id'),
            true
        );
        if ($id === false) {
            $this->setErrorCode(parent::ERROR_KEY_VALUE_NOT_FOUND);
            $this->setErrorMessage('Value ' . $value . ' for key ' . $key .  'not found');
            return self::NULL_ROW_ID;
        }
        return $id;
    }
    
    public function findRows(array $givenRow, int $numResults = 0) : array
    {
        $this->resetError();
        $results = [];
        $givenRowKeys = array_keys($givenRow);
        foreach ($this->theData as $dataRow) {
            $match = true;
            foreach ($givenRowKeys as $key) {
                if ($dataRow[$key] !== $givenRow[$key]) {
                    $match = false;
                }
            }
            if ($match) {
                $results[] = $dataRow;
                if ($numResults > 0 && count($results) === $numResults) {
                    return $results;
                }
            }
        }
        return $results;
    }
}
