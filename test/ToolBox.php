<?php

namespace ThomasInstitut\DataTable;

use Random\RandomException;
use RuntimeException;

class ToolBox
{
    static function printInMemoryDataTableData(DataTable $table): void
    {
        if (!($table instanceof InMemoryDataTable)) {
            return;
        }
        $data = $table->getRawData();
        print "\n";
        foreach($data as $i => $row) {
            print "$i:\n";
            foreach($row as $key => $value) {
                $valueStr = strval($value);
                if (is_string($value)) {
                    $valueStr = "'$valueStr'";
                }
                print "   $key: $valueStr\n";
            }
        }
    }


    static public function getRandomString(int $minLength, int $maxLength = -1): string
    {
        if ($maxLength == -1) {
            $maxLength = $minLength;
        }
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        try {
            $length = random_int($minLength, $maxLength);
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[random_int(0, strlen($characters) - 1)];
            }
            return $randomString;
        } catch (RandomException $e) {
            throw new RuntimeException($e->getMessage());
        }
    }
}