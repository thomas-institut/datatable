<?php

namespace ThomasInstitut\DataTable\Schema;

use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\TimeString\TimeString;

class ColumnDefinition
{
    /**
     * The column type
     * @var ColumnDataType
     */
    public ColumnDataType $type;

    /**
     * If true, the column can be null.
     * @var bool
     */
    public bool $nullable = false;

    /**
     * The column type length.
     *
     * For example, the length of a VARCHAR column.
     *
     * @var int
     */
    public int $typeLength = -1;

    /**
     * The key of the column in the row array.
     *
     * @var string
     */
    public string $rowKey;

    /**
     * The column name in the database if it differs from the row key.
     * @var string|null
     */
    public string|null $dbColumn = null;

    /**
     * If true, the column must be present both in input and output rows.
     * @var bool
     */
    public bool $required = false;


    /**
     * The default value to use for the column if the database requires one for optional columns.
     *
     * @var mixed|null
     */
    public mixed $defaultValue;


    public function __construct(string $rowKey, ColumnDataType $type)
    {
        $this->type = $type;
        $this->rowKey = $rowKey;

        // set a sensible default value
        $this->defaultValue = match ($type) {
            ColumnDataType::VarChar,
            ColumnDataType::Integer, ColumnDataType::Id => -1,
            ColumnDataType::Boolean => false,
            ColumnDataType::Serializable, ColumnDataType::Text => null,
            ColumnDataType::TimeString => '1000-01-01 00:00:00.000000',
        };
    }

    /**
     * @param mixed $value
     * @param ColumnDefinition $columnDefinition
     * @return bool
     */
    public static function valueIsValidForColumn(mixed $value, ColumnDefinition $columnDefinition): bool
    {
        if ($value === null) {
            if ($columnDefinition->type === ColumnDataType::Id) {
                return false;
            }
            return $columnDefinition->nullable;
        }

        return match ($columnDefinition->type) {
            ColumnDataType::Serializable => true,
            ColumnDataType::VarChar => is_string($value)
                && strlen($value) <= $columnDefinition->typeLength,
            ColumnDataType::Text => is_string($value),
            ColumnDataType::Id, ColumnDataType::Integer => is_int($value),
            ColumnDataType::Boolean => is_bool($value),
            ColumnDataType::TimeString => TimeString::isValid($value),
        };
    }

    public function withDefaultValue(mixed $defaultValue): ColumnDefinition
    {
        if (!self::valueIsValidForColumn($defaultValue, $this)) {
            throw new InvalidArgumentException("Invalid default value for column $this->rowKey");
        }
        $this->defaultValue = $defaultValue;
        return $this;
    }

    public function withDbColumn(string $dbColumn): ColumnDefinition
    {
        $this->dbColumn = $dbColumn;
        return $this;
    }

    public function withRequired(bool $required): ColumnDefinition
    {
        if (in_array($this->type, ColumnDataType::NoDefaultTypes)) {
            $required = true;
        }
        $this->required = $required;
        return $this;
    }

    public function withTypeLength(int $typeLength): ColumnDefinition
    {
        $this->typeLength = $typeLength;
        return $this;
    }


    public function withNullable(bool $nullable): ColumnDefinition
    {
        $this->nullable = $nullable;
        $this->defaultValue = null;
        return $this;
    }

}