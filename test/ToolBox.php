<?php

namespace ThomasInstitut\DataTable;

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
}