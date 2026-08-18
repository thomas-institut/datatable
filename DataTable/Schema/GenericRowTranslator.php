<?php

namespace ThomasInstitut\DataTable\Schema;

use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\Exception\InvalidRow;
use ThomasInstitut\DataTable\Exception\InvalidRowFromDatabase;

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
     * @param array<ColumnDefinition> $columnDefinitions The definitions of the columns in the table.
     * @param array<int, ColumnDataType>|null $supportedDataTypes
     * @throws InvalidColumnDefinitionsArray
     */
    public function __construct(private RowValueTranslator $rowValueTranslator,
                                array                      $columnDefinitions,
                                ?array                     $supportedDataTypes = null
    )
    {
        if ($supportedDataTypes === null) {
            $supportedDataTypes = ColumnDataType::cases();
        }
        $errors = ColumnDefArray::validate($columnDefinitions, $supportedDataTypes);
        if ($errors !== []) {
            throw new InvalidColumnDefinitionsArray();
        }
        $this->defsByDbKey = ColumnDefArray::getDefsByDbKey($columnDefinitions);
        $this->defsByRowKey = ColumnDefArray::getDefsByRowKey($columnDefinitions);
        $this->requiredKeys = $this->getRequiredKeys($columnDefinitions);
    }


    /**
     * @inheritDoc
     */
    /**
     * @param array<string, mixed> $inputRow
     * @return array<string, mixed>
     */
    public function inputRowToDb(array $inputRow, bool $failOnMissingRequired = true): array
    {
        $this->validateInputRow($inputRow, $failOnMissingRequired);
        return $this->translateRow($inputRow, false);
    }

    /**
     * @param array<string, mixed> $dbRow
     * @return array<string, mixed>
     */
    public function dbRowToOutputRow(array $dbRow): array
    {
        try {
            return $this->translateRow($dbRow, true);
        } catch (InvalidRow $e) {
            throw new InvalidRowFromDatabase($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws InvalidRow
     */
    /**
     * @param array<string, mixed> $inputRow
     */
    private function validateInputRow(array $inputRow, bool $failOnMissingRequired): void
    {
        if ($failOnMissingRequired) {
            foreach ($this->requiredKeys as $requiredKey) {
                if (!array_key_exists($requiredKey, $inputRow)) {
                    throw new InvalidRow("Required column '$requiredKey' is missing in the input row");
                }
            }
        }
        foreach ($inputRow as $key => $value) {
            if (!isset($this->defsByRowKey[$key])) {
                throw new InvalidRow("Column '$key' is not defined in the schema");
            }
            $columnDef = $this->defsByRowKey[$key];
            if (!ColumnDefinition::valueIsValidForColumn($value, $columnDef)) {
                throw new InvalidRow("Invalid value for column '$key': $value");
            }
        }
    }


    /**
     * @throws InvalidRow
     */
    /**
     * @param array<string, mixed> $theRow
     * @return array<string, mixed>
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
            $type = match($type) {
                ColumnDataType::Id => ColumnDataType::Integer,
                ColumnDataType::ValidUntil, ColumnDataType::ValidFrom => ColumnDataType::TimeString,
                default => $type,
            };

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