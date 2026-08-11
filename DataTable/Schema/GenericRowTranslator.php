<?php

namespace ThomasInstitut\DataTable\Schema;

use RuntimeException;
use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;

readonly class GenericRowTranslator implements RowTranslator
{

    /**
     * The column definitions with the database column names as keys.
     * @var array<string, ColumnDefinition>
     */
    private array $defsByDbKey;

    private array $defsByRowKey;


    /**
     * @param RowValueTranslator $rowValueTranslator
     * @param array<ColumnDefinition> $columnDefinitions The definitions of the columns in the table.
     * @throws InvalidColumnDefinitionsArray
     */
    public function __construct(private RowValueTranslator $rowValueTranslator,
                                array                      $columnDefinitions)
    {
        $errors = ColumnDefArrayValidator::validate($columnDefinitions);
        if (!empty($errors)) {
            throw new InvalidColumnDefinitionsArray();
        }
        $this->defsByDbKey = ColumnDefArray::getDefsByDbKey($columnDefinitions);
        $this->defsByRowKey = ColumnDefArray::getDefsByRowKey($columnDefinitions);
    }


    public function inputRowToDb(array $inputRow): array
    {
        return $this->translateRow($inputRow, false);
    }

    public function dbRowToOutputRow(array $dbRow): array
    {
        return $this->translateRow($dbRow, true);
    }


    /**
     * @param array $theRow
     * @param bool $fromDatabase
     * @return array
     */
    private function translateRow(array $theRow, bool $fromDatabase): array
    {
        $colDefs = $fromDatabase ? $this->defsByDbKey : $this->defsByRowKey;
        $translatedValuesRow = [];

        foreach ($theRow as $key => $value) {
            if (!isset($colDefs[$key])) {
                throw new RuntimeException("Column '$key' is not defined in the DataTable");
            }
            $type = $colDefs[$key]->type;
            if ($type === ColumnDataType::Id) {
                $type = ColumnDataType::Integer;
            }

            if ($fromDatabase) {
                $translatedValuesRow[$key] = $this->rowValueTranslator->dbValueToRowValue($value, $type);
            } else {
                $translatedValuesRow[$key] = $this->rowValueTranslator->rowValueToDbValue($value, $type);
            }
        }

        $translatedRow = [];

        foreach ($colDefs as $key => $columnDefinition) {
            if (!array_key_exists($key, $translatedValuesRow)) {
                continue;
            }
            $translatedKey = $fromDatabase
                ? $columnDefinition->rowKey
                : $columnDefinition->dbColumn ?? $columnDefinition->rowKey;
            $translatedRow[$translatedKey] = $translatedValuesRow[$key];
        }
        return $translatedRow;
    }
}