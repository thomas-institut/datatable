<?php
/*
 * The MIT License
 *
 * Copyright 2019 Rafael Nájera <rafael@najera.ca>.
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


use Exception;
use RuntimeException;

class RandomIdGenerator implements iIdGenerator
{

    const ERROR_RANDOM_NUMBER_GENERATOR_ERROR = 1001;
    const ERROR_MAX_ATTEMPTS_REACHED = 1002;

    /**
     * @var int
     */
    private $minId;

    /**
     * @var int
     */
    private $maxId;

    /**
     * @var int
     */
    private $maxAttempts;

    public function __construct(int $min = 1, int $max = PHP_INT_MAX, int $maxAttempts = 1000)
    {
        $this->minId = $min;
        $this->maxId = $max;
        $this->maxAttempts = $maxAttempts;
    }

    public function getOneUnusedId(DataTable $dataTable): int
    {
        for ($i = 0; $i < $this->maxAttempts; $i++) {
            try {
                $theId = random_int($this->minId, $this->maxId);
            } catch (Exception $e) {  // @codeCoverageIgnore
                throw new RuntimeException($e->getMessage(), self::ERROR_RANDOM_NUMBER_GENERATOR_ERROR); // @codeCoverageIgnore
            }
            if (!$dataTable->rowExists($theId)) {
                return $theId;
            }
        }
        // No unused Id found, let the client know via an Exception
        throw new RuntimeException("Could not generate an unused Id after $this->maxAttempts attempts", self::ERROR_MAX_ATTEMPTS_REACHED);
    }
}