<?php

namespace ThomasInstitut\DataTable\Schema;

use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\TimeString\TimeString;

class ColumnDefinition
{
    /**
     * If true, the column can be null.
     */
    public bool $nullable = false;

    /**
     * The column type length.
     *
     * For example, the length of a VARCHAR column.
     */
    public int $typeLength = -1;

    /**
     * The column name in the database if it differs from the row key.
     */
    public string|null $dbColumn = null;

    /**
     * If true, the column must be present both in input and output rows.
     */
    public bool $required = false;


    /**
     * A flag to indicate if the default value was explicitly set.
     * If false, datatables normally will ignore the default value.
     *
     * Prefer using the method ColumnDefinition::withDefaultValue() to manipulate default values
     *
     *
     * @see ColumnDefinition::withDefaultValue()
     */
    public bool $defaultValueExplicitlySet = false;

    /**
     * The default value to use for the column.
     *
     * Prefer using the method ColumnDefinition::withDefaultValue() to manipulate default values
     *
     * @var mixed|null
     * @see ColumnDefinition::withDefaultValue()
     */
    public mixed $defaultValue = null;


    public function __construct(/**
        * The key of the column in the row array.
        */
        public string $rowKey, /**
        * The column type
        */
        public ColumnDataType $type)
    {
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function valueIsValidForColumn(mixed $value, ColumnDefinition $columnDefinition): bool
    {
        if ($value === null) {
            if ($columnDefinition->type === ColumnDataType::Id) {
                return false;
            }
            return $columnDefinition->nullable;
        }

        if ($columnDefinition->type === ColumnDataType::VarChar && $columnDefinition->typeLength < 0) {
            throw new InvalidArgumentException("Invalid type length $columnDefinition->typeLength for column '$columnDefinition->rowKey' of type '{$columnDefinition->type->value}'");

        }

        return match ($columnDefinition->type) {
            ColumnDataType::Serializable => true,
            ColumnDataType::VarChar => is_string($value)
                && strlen($value) <= $columnDefinition->typeLength,
            ColumnDataType::Text => is_string($value),
            ColumnDataType::Id, ColumnDataType::Integer => is_int($value),
            ColumnDataType::Boolean => is_bool($value),
            ColumnDataType::TimeString, ColumnDataType::ValidFrom, ColumnDataType::ValidUntil => TimeString::isValid($value),
        };
    }

    /**
     * Set the default value for the column and flags it as explicitly set.
     *
     * @throws InvalidArgumentException
     */
    public function withDefaultValue(mixed $defaultValue): ColumnDefinition
    {
        if (!self::valueIsValidForColumn($defaultValue, $this)) {
            throw new InvalidArgumentException("Invalid default value ($defaultValue) for column '$this->rowKey' of type '{$this->type->value}'");
        }
        $this->defaultValue = $defaultValue;
        $this->defaultValueExplicitlySet = true;
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