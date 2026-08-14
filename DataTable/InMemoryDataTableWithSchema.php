<?php

namespace ThomasInstitut\DataTable;

use ThomasInstitut\DataTable\IdGenerator\IdGenerator;
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\DataTableSchema;
use ThomasInstitut\DataTable\Schema\NoOpRowValueTranslator;
use ThomasInstitut\DataTable\Schema\SupportedSearchCondition;

class InMemoryDataTableWithSchema extends GenericDataTableWithSchema
{
    public function __construct(DataTableSchema $dataTableSchema, array|null &$data = null, ?IdGenerator $idGenerator = null)
    {
        parent::__construct(new InMemoryDataTable($data, $idGenerator), $dataTableSchema, new NoOpRowValueTranslator(), SupportedSearchCondition::reasonableDefaults(), ColumnDataType::cases());
    }
}