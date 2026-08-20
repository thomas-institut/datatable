<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable;

use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\ColumnDefArray;
use ThomasInstitut\DataTable\Schema\ColumnDefinition;
use ThomasInstitut\DataTable\Schema\DataTableSchema;
use ThomasInstitut\DataTable\Schema\MySqlRowValueTranslator;
use ThomasInstitut\DataTable\Schema\SupportedSearchCondition;

class MySqlUnitemporalDataTableWithSchema extends GenericUnitemporalDataTableWithSchema
{

    public function __construct(MySqlUnitemporalDataTable $unitemporalDataTable, DataTableSchema $schema)
    {
        $validFromDefs = ColumnDefArray::getColumnDefsForType($schema->columnDefinitions, ColumnDataType::ValidFrom);
        if ($validFromDefs === []) {
            $schema->columnDefinitions[] = new ColumnDefinition('valid_from', ColumnDataType::ValidFrom);
        }
        $validUntilDefs = ColumnDefArray::getColumnDefsForType($schema->columnDefinitions, ColumnDataType::ValidUntil);
        if ($validUntilDefs === []) {
            $schema->columnDefinitions[] = new ColumnDefinition('valid_until', ColumnDataType::ValidUntil);
        }
        parent::__construct($unitemporalDataTable, $schema, new MySqlRowValueTranslator(), SupportedSearchCondition::reasonableDefaults(), ColumnDataType::cases());
    }

}