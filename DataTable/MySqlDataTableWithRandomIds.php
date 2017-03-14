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

class MySqlDataTableWithRandomIds extends MySqlDataTable
{
    
    private $minId, $maxId;
    private $maxTries = 1000;
    
    /**
     * 
     * $min and $max should be carefully chosen so that
     * the method to get new unused id doesn't take too
     * long. 
     * 
     * @param type $theDb
     * @param type $tn
     * @param type $min   Minimum Id
     * @param type $max   Max Id
     */
    public function __construct($theDb, $tn, $min=1, $max=PHP_INT_MAX) {
        $this->minId = $min;
        $this->maxId = $max;
        
        parent::__construct($theDb, $tn);
        
    }
    
    public function getOneUnusedId(){
        for ($i = 0; $i < $this->maxTries; $i++){
            $theId = random_int($this->minId, $this->maxId);
            if (!$this->rowExistsById($theId)){
                return $theId;
            }
        }
        // This part of the code should almost never run in real life!
        //error_log("WARNING: Reached max tries ($this->maxTries) searching for a new rowID for table '$this->tableName', will return maxId+1");
        return $this->getMaxId()+1;
    }
    
}