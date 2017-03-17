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

use \PDO as PDO;

class InMemoryDataTable extends DataTable 
{
    
    private $theData = [];
    
    public function getAllRows() {
        return $this->theData;
    }
    
    public function rowExistsById($rowId){
        return isset($this->theData[$rowId]);
    }
    
    public function realCreateRow($theRow){
        $this->theData[$theRow['id']] = [];
        $this->theData[$theRow['id']] = $theRow;
        return $theRow['id'];
    }
    
    public function realDeleteRow($rowId) {
        unset($this->theData[$rowId]);
        return true;
    }
    
    public function realUpdateRow($theRow) {
        $keys = array_keys($theRow);
        $id = $theRow['id'];
        
        foreach ($keys as $k){
            $this->theData[$id][$k] = $theRow[$k];
        }
        return true;
    }
    
    public function getMaxId() {
        if (count($this->theData) !== 0){
            return max(array_column($this->theData, 'id'));
        }
        else{
            return 0;
        }
    }
    
    public function getRow($rowId) {
        if ($this->rowExistsById($rowId)) {
            return $this->theData[$rowId];
        }
        return false;
    }
    
    public function getIdForKeyValue($key, $value) {
        return array_search($value, array_column($this->theData, $key, 'id'), TRUE);
    }
    
    public function findRow($givenRow) {
        $givenRowKeys = array_keys($givenRow);
        foreach($this->theData as $dataRow){
            $match = true;
            foreach ($givenRowKeys as $key){
                if ($dataRow[$key] !== $givenRow[$key]){
                    $match = false;
                }
            }
            if ($match){
                return ($dataRow['id']);
            }
        }
        return false;
    }
}