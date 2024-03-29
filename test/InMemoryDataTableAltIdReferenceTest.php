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
require '../vendor/autoload.php';

require_once 'DataTableReferenceTestCase.php';

/**
 * Description of DataTableTest
 *
 * @author Rafael Nájera <rafael@najera.ca>
 */
class InMemoryDataTableAltIdReferenceTest extends DataTableReferenceTestCase
{

    static private ?InMemoryDataTable $motherTable = null;
    static private ?array $theData = null;
    
    public function getTestDataTable(bool $resetTable = true, bool $newSession = false) : GenericDataTable
    {
        if (self::$motherTable === null) {  // first table to serve
            self::$theData = [];
            self::$motherTable = new InMemoryDataTable(self::$theData);
            self::$motherTable->setIdColumnName('tid');
            $dt = self::$motherTable;
        } else {
            $dt = new InMemoryDataTable(self::$theData);
            $dt->setIdColumnName('tid');
        }

        if ($resetTable) {
            self::$theData = [];
        }
        $dt->setLogger($this->getLogger()->withName('InMemoryDT'));

        return $dt;
    }

    public function multipleDataAccessSessionsAvailable(): bool
    {
        return false;
    }
}
