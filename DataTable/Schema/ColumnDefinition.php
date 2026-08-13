<?php

namespace ThomasInstitut\DataTable\Schema;

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
     * @codeCoverageIgnore
     */
    public function __construct(string $rowKey, ColumnDataType $type)
    {
        $this->type = $type;
        $this->rowKey = $rowKey;
    }

    /**
     * @codeCoverageIgnore
     */
    public function withDbColumn(string $dbColumn): ColumnDefinition
    {
        $this->dbColumn = $dbColumn;
        return $this;
    }

    /**
     * @codeCoverageIgnore
     */
    public function withRequired(bool $required): ColumnDefinition
    {
        $this->required = $required;
        return $this;
    }

    /**
     * @codeCoverageIgnore
     */
    public function withTypeLength(int $typeLength): ColumnDefinition
    {
        $this->typeLength = $typeLength;
        return $this;
    }

    /**
     * @codeCoverageIgnore
     */
    public function withNullable(bool $nullable): ColumnDefinition
    {
        $this->nullable = $nullable;
        return $this;
    }

}