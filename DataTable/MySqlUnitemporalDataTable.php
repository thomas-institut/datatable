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

use PDO;

/**
 * Compatibility wrapper for PDO-based MySql unitemporal tables.
 */
class MySqlUnitemporalDataTable extends PdoUnitemporalDataTable
{
    /**
     * @param PDO|PdoProvider $pdoOrProvider initialized PDO connection or provider
     * @param string $tableName SQL table name
     * @param string $idColumnName
     */
    public function __construct(PDO|PdoProvider $pdoOrProvider, string $tableName, string $idColumnName = self::DEFAULT_ID_COLUMN_NAME)
    {
        parent::__construct($pdoOrProvider, $tableName, new MySqlDialect(), $idColumnName);
    }
}
