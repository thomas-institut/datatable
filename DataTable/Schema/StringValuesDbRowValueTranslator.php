<?php

namespace ThomasInstitut\DataTable\Schema;

use ThomasInstitut\DataTable\Exception\InvalidArgumentException;

readonly class StringValuesDbRowValueTranslator implements RowValueTranslator
{
    private string $dbNullValue;
    private string $literalStringPrefix;

    /**
     * Constructor for the class.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(private StringValuesDbRowValueTranslatorOptions $options = new StringValuesDbRowValueTranslatorOptions())
    {
        $this->validateOptions($this->options);
        $this->literalStringPrefix = $this->options->literalStringPrefix;
        $this->dbNullValue = $this->options->dbNullValue;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateOptions(StringValuesDbRowValueTranslatorOptions $options): void
    {
        if (trim($options->literalStringPrefix) === '') {
            throw new InvalidArgumentException(sprintf('Literal string prefix must be a non-empty string, "%s" given', $options->literalStringPrefix));
        }

        $bannedStrings = [ $options->literalStringPrefix];

        if (trim($options->dbNullValue) === '' || in_array($options->dbNullValue, $bannedStrings, true)) {
            throw new InvalidArgumentException(sprintf('Null value must be a non-empty string or one of [ %s ], "%s" given', implode(', ', $bannedStrings), $options->dbNullValue));
        }
        $bannedStrings[] = $options->dbNullValue;

        if (trim($options->falseValue) === '' || in_array($options->falseValue, $bannedStrings, true)) {
            throw new InvalidArgumentException(sprintf('False value must be a non-empty string or one of [ %s ], "%s" given', implode(', ', $bannedStrings), $options->falseValue));
        }
        $bannedStrings[] = $options->falseValue;

        if (trim($options->trueValue) === '' || in_array($options->trueValue, $bannedStrings, true)) {
            throw new InvalidArgumentException(sprintf('True value must be a non-empty string or one of [ %s ], "%s" given', implode(', ', $bannedStrings), $options->trueValue));
        }
    }

    public function rowValueToDbValue(mixed $value, ColumnDataType $type): string
    {
        if ($value === null) {
            return $this->dbNullValue;
        }
        $stringValue = match ($type) {
            ColumnDataType::Serializable => serialize($value),
            ColumnDataType::Integer, ColumnDataType::Id => (string)$value,
            ColumnDataType::Boolean => $value ? $this->options->trueValue : $this->options->falseValue,
            ColumnDataType::VarChar, ColumnDataType::Text,
            ColumnDataType::TimeString, ColumnDataType::ValidUntil, ColumnDataType::ValidFrom => $value,
        };
        return $this->encodeString($stringValue);
    }

    private function encodeString(string $str): string
    {
        if ($str === $this->dbNullValue || str_starts_with($str, $this->literalStringPrefix)) {
            return $this->literalStringPrefix . $str;
        } else {
            return $str;
        }
    }

    private function decodeString(string $str): string
    {
        if (str_starts_with($str, $this->literalStringPrefix)) {
            return substr($str, strlen($this->literalStringPrefix));
        }
        return $str;
    }

    public function dbValueToRowValue(mixed $value, ColumnDataType $type): mixed
    {
        if ($value === $this->dbNullValue) {
            return null;
        }
        $decodedValue = $this->decodeString($value);
        return match ($type) {
            ColumnDataType::Serializable => unserialize($decodedValue),
            ColumnDataType::Integer, ColumnDataType::Id => intval($decodedValue),
            ColumnDataType::Boolean => $decodedValue === $this->options->trueValue,
            ColumnDataType::VarChar, ColumnDataType::Text,
            ColumnDataType::TimeString, ColumnDataType::ValidUntil, ColumnDataType::ValidFrom => $decodedValue,
        };
    }
}