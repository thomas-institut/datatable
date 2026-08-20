<?php

namespace ThomasInstitut\DataTable;


use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\DataTableSchema;
use ThomasInstitut\DataTable\Schema\MySqlRowValueTranslator;
use ThomasInstitut\DataTable\Schema\SupportedSearchCondition;


class MySqlDataTableWithSchema extends GenericDataTableWithSchema
{

    /**
     * Constructs a new instance of MySqlDataTableWithSchema based on a MySqlDataTable and a DataTableSchema.
     *
     * The MySqlDataTable must be already created in the database with a schema that matches the provided DataTableSchema.
     *
     * @throws InvalidColumnDefinitionsArray
     * @see MySqlDataTable::createTable()
     */
    public function __construct(private readonly MySqlDataTable $mySqlDataTable, private readonly DataTableSchema $dataTableSchema)
    {
        parent::__construct($this->mySqlDataTable, $this->dataTableSchema, new MySqlRowValueTranslator(), SupportedSearchCondition::reasonableDefaults(), ColumnDataType::cases());
    }

}