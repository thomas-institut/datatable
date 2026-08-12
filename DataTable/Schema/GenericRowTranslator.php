<?php

namespace ThomasInstitut\DataTable\Schema;

use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\Exception\InvalidRow;

readonly class GenericRowTranslator implements RowTranslator
{

    /**
     * The column definitions with the database column names as keys.
     * @var array<string, ColumnDefinition>
     */
    private array $defsByDbKey;

    /**
     * The column definitions with the row column names as keys.
     * @var array<string, ColumnDefinition>
     */
    private array $defsByRowKey;


    /**
     * The row keys that are required.
     * @var array<string>
     */
    private array $requiredKeys;


    /**
     * @param RowValueTranslator $rowValueTranslator
     * @param array<ColumnDefinition> $columnDefinitions The definitions of the columns in the table.
     * @throws InvalidColumnDefinitionsArray
     */
    public function __construct(private RowValueTranslator $rowValueTranslator,
                                 array              $columnDefinitions)
    {
        $errors = ColumnDefArrayValidator::validate($columnDefinitions);
        if (!empty($errors)) {
            throw new InvalidColumnDefinitionsArray();
        }
        $this->defsByDbKey = ColumnDefArray::getDefsByDbKey($columnDefinitions);
        $this->defsByRowKey = ColumnDefArray::getDefsByRowKey($columnDefinitions);
        $this->requiredKeys = $this->getRequiredKeys($columnDefinitions);
    }


    /**
     * @throws InvalidRow
     */
    public function inputRowToDb(array $inputRow): array
    {
        $this->validateInputRow($inputRow);
        return $this->translateRow($inputRow, false);
    }

    /**
     * @throws InvalidRow
     */
    public function dbRowToOutputRow(array $dbRow): array
    {
        return $this->translateRow($dbRow, true);
    }

    /**
     * @param array $inputRow
     * @throws InvalidRow
     */
    private function validateInputRow(array $inputRow): void
    {
        foreach ($this->requiredKeys as $requiredKey) {
            if (!array_key_exists($requiredKey, $inputRow)) {
                throw new InvalidRow("Required column '$requiredKey' is missing in the input row");
            }
        }
        foreach($inputRow as $key => $value) {
            if (!isset($this->defsByRowKey[$key])) {
                throw new InvalidRow("Column '$key' is not defined in the schema");
            }
            $columnDef = $this->defsByRowKey[$key];
            if (!ColumnValueValidator::validate($value, $columnDef)) {
                throw new InvalidRow("Invalid value for column '$key': $value");
            }
        }
    }


    /**
     * @param array $theRow
     * @param bool $fromDatabase
     * @return array
     * @throws InvalidRow
     */
    private function translateRow(array $theRow, bool $fromDatabase): array
    {
        $colDefs = $fromDatabase ? $this->defsByDbKey : $this->defsByRowKey;
        $translatedValuesRow = [];

        foreach ($theRow as $key => $value) {
            if (!isset($colDefs[$key])) {
                throw new InvalidRow("Column '$key' is not defined in the DataTable");
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

    /**
     * @param array<ColumnDefinition> $columnDefinitions
     * @return array<string>
     */
    private function getRequiredKeys(array $columnDefinitions): array
    {
        $requiredKeys = [];
        foreach ($columnDefinitions as $columnDefinition) {
            if ($columnDefinition->required) {
                $requiredKeys[] = $columnDefinition->rowKey;
            }
        }
        return $requiredKeys;
    }
}