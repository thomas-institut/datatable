<?php


namespace DataTable;


use InvalidArgumentException;
use RuntimeException;

class UnitemporalDataTable extends DataTable
{

    /**
     * @var DataTable
     */
    private $theDataTable;


    // Error codes
    const ERROR_INVALID_TIME = 2010;


    // Other constants
    const END_OF_TIMES = '9999-12-31 23:59:59.999999';
    const MYSQL_DATE_FORMAT  = 'Y-m-d H:i:s';

    const REAL_ROW_ID_FIELD = 'udt_id';

    /**
     * @param int $rowId
     * @return bool true if the row with the given Id exists
     */
    public function rowExists(int $rowId): bool
    {
        return $this->rowExistsWithTime(self::now());
    }

    public function rowExistsWithTime(int $rowId, string $timeString) : bool {
        return false;
    }

    /**
     * Gets the row with the given row Id.
     * If the row does not exist throws an InvalidArgument exception
     *
     * @param int $rowId
     * @return array The row
     * @throws InvalidArgumentException
     */
    public function getRow(int $rowId): array
    {
        // TODO: Implement getRow() method.
    }

    /**
     * Gets all rows in the table
     *
     * @return array
     */
    public function getAllRows(): array
    {
        // TODO: Implement getAllRows() method.
    }

    /**
     * Deletes the row with the given Id.
     * If there's no row with the given Id it must return false
     *
     * @param int $rowId
     * @return bool
     */
    public function deleteRow(int $rowId): int
    {
        // TODO: Implement deleteRow() method.
    }

    /**
     * Searches the table for rows with the same data as the given row
     *
     * Only the keys given in $theRow are checked; so, for example,
     * if $theRow is missing a key that exists in the actual rows
     * in the table, those missing keys are ignored and the method
     * will return any row that matches exactly the given keys independently
     * of the missing ones.
     *
     * if $maxResults > 0, an array of max $maxResults will be returned
     * if $maxResults <= 0, all results will be returned
     *
     * @param array $rowToMatch
     * @param int $maxResults
     * @return array the results
     */
    public function findRows(array $rowToMatch, int $maxResults = 0): array
    {
        // TODO: Implement findRows() method.
    }

    /**
     * Returns the id of one row in which $row[$key] === $value
     * or false if such a row cannot be found or an error occurred whilst
     * trying to find it.
     *
     * @param string $key
     * @param mixed $value
     * @return int
     */
    public function getIdForKeyValue(string $key, $value): int
    {
        // TODO: Implement getIdForKeyValue() method.
    }

    /**
     * @return int the max id in the table
     */
    public function getMaxId(): int
    {
        // TODO: Implement getMaxId() method.
    }

    /**
     * Creates a row in the table, returns the id of the newly created
     * row.
     *
     * @param array $theRow
     * @return int
     */
    protected function realCreateRow(array $theRow): int
    {
        // TODO: Implement realCreateRow() method.
    }

    /**
     * Updates the given row, which must have a valid Id.
     * If there's not row with that id, it throw an InvalidArgument exception.
     *
     * Must throw a Runtime Exception if the row was not updated
     *
     * @param array $theRow
     * @return void
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    protected function realUpdateRow(array $theRow): void
    {
        // TODO: Implement realUpdateRow() method.
    }


    /**
     * Returns the current time in MySQL format with microsecond precision
     *
     * @return string
     */
    public static function now() : string
    {
        return self::getTimeStringFromTimeStamp(microtime(true));
    }

    /**
     * @param float $timeStamp
     * @return string
     */
    private static function getTimeStringFromTimeStamp(float $timeStamp) : string
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
    public static function getTimeStringFromVariable($timeVar) : string
    {
        if (is_numeric($timeVar)) {
            return self::getTimeStringFromTimeStamp((float) $timeVar);
        }
        if (is_string($timeVar)) {
            return  self::getGoodTimeString($timeVar);
        }
        return '';
    }


    public static function getGoodTimeString(string $str) {
        if (preg_match('/^\d\d\d\d-\d\d-\d\d$/', $str)) {
            $str .= ' 00:00:00.000000';
        } else {
            if (preg_match('/^\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d$/', $str)){
                $str .= '.000000';
            }
        }
        if (!self::isTimeStringValid($str)) {
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
    public static function isTimeStringValid(string $str) : bool {
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

    private function throwExceptionForInvalidTime(string $timeString, string $context) : void {
        $this->setErrorCode(self::ERROR_INVALID_TIME);
        $this->setErrorMessage("Invalid time given for $context : \"$timeString\"");
        throw new InvalidArgumentException($this->getErrorMessage(), $this->getErrorCode());
    }

    /**
     * Searches the datatable according to the given $searchSpec
     *
     * $searchSpec is an array of conditions.
     *
     * If $searchType is SEARCH_AND, the row must satisfy:
     *      $searchSpec[0] && $searchSpec[1] && ...  && $searchSpec[n]
     *
     * if  $searchType is SEARCH_OR, the row must satisfy the negation of the spec:
     *
     *      $searchSpec[0] || $searchSpec[1] || ...  || $searchSpec[n]
     *
     *
     * A condition is an array of the form:
     *
     *  $condition = [
     *      'column' => 'columnName',
     *      'condition' => one of (EQUAL_TO, NOT_EQUAL_TO, LESS_THAN, LESS_OR_EQUAL_TO, GREATER_THAN, GREATER_OR_EQUAL_TO)
     *      'value' => someValue
     * ]
     *
     * Notice that each condition type has a negation:
     *      EQUAL_TO  <==> NOT_EQUAL_TO
     *      LESS_THAN  <==>  GREATER_OR_EQUAL_TO
     *      LESS_OR_EQUAL_TO <==> GREATER_THAN
     *
     * if $maxResults > 0, an array of max $maxResults will be returned
     * if $maxResults <= 0, all results will be returned
     *
     * @param array $searchSpec
     * @param int $searchType
     * @param int $maxResults
     * @return array
     */
    public function search(array $searchSpec, int $searchType = self::SEARCH_AND, int $maxResults = 0): array
    {
        // TODO: Implement search() method.
    }

    /**
     * Returns the max value in the given column.
     *
     * The actual column must exist and be numeric for the actual value returned
     * to be meaningful. Implementations may choose to throw a RunTime exception
     * in this case.
     *
     * @param string $columnName
     * @return int
     */
    public function getMaxValueInColumn(string $columnName): int
    {
        // TODO: Implement getMaxValueInColumn() method.
    }
}