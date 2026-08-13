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
        $supportedSearchConditions = [
            new SupportedSearchCondition(ColumnDataType::Text, SearchCondition::cases()),
            new SupportedSearchCondition(ColumnDataType::VarChar, SearchCondition::cases()),
            new SupportedSearchCondition(ColumnDataType::Integer, SearchCondition::cases()),
            new SupportedSearchCondition(ColumnDataType::Boolean, [SearchCondition::Equals, SearchCondition::NotEquals]),
        ];

        parent::__construct(new InMemoryDataTable($data, $idGenerator), $dataTableSchema, new NoOpRowValueTranslator(), $supportedSearchConditions, ColumnDataType::cases());
    }
}