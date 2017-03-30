<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace DataTable;

use \PDO;

/**
 * Description of MySqlUnitemporalDataTable
 *
 * @author Rafael Nájera <rafael.najera@uni-koeln.de>
 */
class MySqlUnitemporalDataTable extends MySqlDataTable {
    
    const END_OF_TIMES = '9999-12-31 23:59:59.999999';
    const MYSQL_DATE_FORMAT  = 'Y-m-d H:i:s';
    
    /**
     * 
     * @param \PDO $theDb  initialized PDO connection
     * @param string $tn  SQL table name
     */
    public function __construct($theDb, $tn) {
        
        parent::__construct($theDb, $tn);
        
        // Override rowExistsById statement
        $this->statements['rowExistsById'] = 
                $this->dbConn->prepare('SELECT id FROM ' . $this->tableName . 
                        ' WHERE id= :id AND valid_until=' . 
                        $this->quote(self::END_OF_TIMES));
    }
    
    
    /**
     * Returns true if the table in the DB has 
     * at least an id column of type int
     */
    protected function realIsDbTableValid()
    {
        if (parent::realIsDbTableValid() === false) {
            return false;
        }
        // Check valid_from column
        $result = $this->dbConn->query(
                'SHOW COLUMNS FROM ' . $this->tableName . ' LIKE \'valid_from\'');
        if ($result->rowCount() != 1) {
            return false;
        }
        $columnInfo = $result->fetch(PDO::FETCH_ASSOC);
        if (!preg_match('/^datetime/', $columnInfo['Type'])) {
            return false;
        }
        // Check valid_until column
        $result2 = $this->dbConn->query(
                'SHOW COLUMNS FROM ' . $this->tableName . ' LIKE \'valid_until\'');
        if ($result2->rowCount() != 1) {
            return false;
        }
        $columnInfo2 = $result2->fetch(PDO::FETCH_ASSOC);
        if (!preg_match('/^datetime/', $columnInfo2['Type'])) {
            return false;
        }
        return true;
    }
    
    public function realCreateRow($theRow) {
        return $this->realCreateRowWithTime($theRow, self::now());
    }
    
    public function realCreateRowWithTime($theRow, $time)
    {
        if (!$this->isDbTableValid()) {
            return false;
        }
        $keys = array_keys($theRow);
        $theRow['valid_from'] = $time;
        $theRow['valid_until'] = self::END_OF_TIMES;
        
        //print "Ready to real create row now\n";
        //var_dump($theRow);
        
        return parent::realCreateRow($theRow);
    }

    /**
     * 
     * @param type $theRow
     * @param type $time
     */
    public function makeRowInvalid($theRow, $time)
    {
        $sql = 'UPDATE ' . $this->tableName . ' SET ' . 
                ' valid_until=' . $this->quote($time). 
                ' WHERE id=' . $theRow['id'] .
                ' AND valid_from = ' . $this->quote($theRow['valid_from']) . 
                ' AND valid_until= ' . $this->quote($theRow['valid_until']);
        if ($this->dbConn->query($sql) === false) {
            // Can't get here in testing: query only fails on MySQL failure
            return false; // @codeCoverageIgnore  
        }
        return $theRow['id'];
    }
    
    public function realUpdateRow($theRow)
    {
        $oldRow = $this->realGetRow($theRow['id']);
        $now = self::now();
        $this->makeRowInvalid($oldRow, $now);
        foreach(array_keys($oldRow) as $key) {
            if ($key === 'valid_from' or $key==='valid_until') {
                continue;
            }
            if (!array_key_exists($key, $theRow)) {
                $theRow[$key] = $oldRow[$key];
            }   
        }
        return $this->realCreateRowWithTime($theRow, $now);
    }
    
    public function getAllRows() 
    {
        return $this->getAllRowsWithTime(self::now());
    }
    
    public function getAllRowsWithTime($time) 
    {
        if (!$this->isDbTableValid()) {
            return false;
        }
        $time = $this->quote($time);
        $r = $this->dbConn->query('SELECT * FROM ' . $this->tableName . 
                ' WHERE valid_from <= ' . $time . 
                ' AND valid_until > ' . $time);
        if ($r === false) {
            // Can't get here in testing: query only fails on MySQL failure
            return false; // @codeCoverageIgnore  
        }
        return $this->forceIntIds($r->fetchAll(PDO::FETCH_ASSOC));
    }
    
    
    public function getRow($rowId) {
        return $this->realGetRow($rowId, true);
    }
    public function realGetRow($rowId, $makeCompatible=false) {
        $theRow = $this->getRowWithTime($rowId, self::now());
        if ($theRow === false) {
            return false;
        }
        if ($makeCompatible) {
            unset($theRow['valid_from']);
            unset($theRow['valid_until']);
        }
        return $theRow;
    }
    
    public function getRowWithTime($rowId, $time) {
        
        if (!$this->isDbTableValid()) {
            return false;
        }
      
        $time = $this->quote($time);
        $query = 'SELECT * FROM ' . $this->tableName . 
                        ' WHERE `id`=' . $rowId . 
                        ' AND `valid_from`<=' . $time . 
                        ' AND `valid_until`>' . $time . 
                        ' ORDER BY `valid_from` DESC' . 
                        ' LIMIT 1';
        $r = $this->dbConn
                ->query($query)
                ->fetch(PDO::FETCH_ASSOC);
        
        if ($r === false) {
            // Can't get here in testing: query only fails on MySQL failure
            return false; // @codeCoverageIgnore  
        }
        $r['id'] = (int) $r['id'];
        return $r;
        
    }
    
    public function realfindRows($theRow, $maxResults) {
        return $this->realfindRowsWithTime($theRow, $maxResults, self::now());
    }
    
    /**
     * 
     * @param array $theRow
     * @param int $maxResults  
     * @return int/array if $maxResults == 1, returns a single int, if not, 
     *                   returns an array of ints. Returns false if not
     *                   rows are found
     */
    public function realfindRowsWithTime($theRow, $maxResults, $time)
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
        $time = $this->quote($time);
        $sql = 'SELECT * FROM ' . $this->tableName . ' WHERE ' . 
                implode(' AND ', $conditions) . 
                ' AND valid_from <= ' . $time . 
                ' AND valid_until > ' . $time;
        if ($maxResults){
            $sql .= ' LIMIT ' . $maxResults;
        }
        //print "Searching for a row: ";
        //var_dump($theRow);
       //s print "SQL = $sql\n";
        $r = $this->dbConn->query($sql);
        if ( $r === false) {
            return false;
        }

        return $this->forceIntIds($r->fetchAll(PDO::FETCH_ASSOC));
    }
    
    
    public function realDeleteRow($rowId)
    {
        $oldRow = $this->realGetRow($rowId);
        return $this->makeRowInvalid($oldRow, self::now()) !== false ;
    }
    
    public static function now()
    {
        $timeNow = microtime(true);
        $intTime =  floor($timeNow);
        $date=date(self::MYSQL_DATE_FORMAT, $intTime);
        $microSeconds = (int) floor(($timeNow - $intTime)*1000000);
        return sprintf("%s.%06d", $date, $microSeconds);
    }
}
