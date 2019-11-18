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

/**
 * Class TimeString
 *
 * Static methods to deal with strings that have or must have a MySql datetime format
 *
 * @package DataTable
 */

class TimeString
{

    const MYSQL_DATE_FORMAT  = 'Y-m-d H:i:s';

    /**
     * Returns the current time in MySQL format with microsecond precision
     *
     * @return string
     */
    public static function now() : string
    {
        return self::fromTimeStamp(microtime(true));
    }

    /**
     * @param float $timeStamp
     * @return string
     */
    public static function fromTimeStamp(float $timeStamp) : string
    {
        $intTime =  floor($timeStamp);
        $date=date(self::MYSQL_DATE_FORMAT, $intTime);
        $microSeconds = (int) floor(($timeStamp - $intTime)*1000000);
        return sprintf("%s.%06d", $date, $microSeconds);
    }

    /**
     * Returns a valid timeString if the variable can be converted to a time
     * If not, returns an empty string (which will be immediately recognized as
     * invalid by isTimeStringValid
     *
     * @param float|int|string $timeVar
     * @return string
     */
    public static function fromVariable($timeVar) : string
    {
        if (is_numeric($timeVar)) {
            return self::fromTimeStamp((float) $timeVar);
        }
        if (is_string($timeVar)) {
            return  self::fromString($timeVar);
        }
        return '';
    }


    public static function fromString(string $str) {
        if (preg_match('/^\d\d\d\d-\d\d-\d\d$/', $str)) {
            $str .= ' 00:00:00.000000';
        } else {
            if (preg_match('/^\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d$/', $str)){
                $str .= '.000000';
            }
        }
        if (!self::isValid($str)) {
            return '';
        }
        return $str;
    }
    /**
     * Returns true if the given string is a valid timeString
     *
     * @param string $str
     * @return bool
     */
    public static function isValid(string $str) : bool {
        if ($str === '') {
            return false;
        }
        $matches = [];
        if (preg_match('/^\d\d\d\d-(\d\d)-(\d\d) (\d\d):(\d\d):(\d\d)\.\d\d\d\d\d\d$/', $str, $matches) !== 1) {
            return false;
        }
        if (intval($matches[1]) > 12) {
            return false;
        }
        if (intval($matches[2]) > 31) {
            return false;
        }
        if (intval($matches[3]) > 23) {
            return false;
        }
        if (intval($matches[4]) > 59) {
            return false;
        }
        if (intval($matches[5]) > 59) {
            return false;
        }
        return true;
    }

}