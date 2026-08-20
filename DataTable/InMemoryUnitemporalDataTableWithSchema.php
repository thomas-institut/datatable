<?php

namespace ThomasInstitut\DataTable;

use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\ColumnDefArray;
use ThomasInstitut\DataTable\Schema\ColumnDefinition;
use ThomasInstitut\DataTable\Schema\DataTableSchema;
use ThomasInstitut\DataTable\Schema\NoOpRowValueTranslator;
use ThomasInstitut\DataTable\Schema\SupportedSearchCondition;

class InMemoryUnitemporalDataTableWithSchema extends GenericUnitemporalDataTableWithSchema
{
    public function __construct(DataTableSchema $schema) {
        $udt = new InMemoryUnitemporalDataTable();
        $validFromDefs = ColumnDefArray::getColumnDefsForType($schema->columnDefinitions, ColumnDataType::ValidFrom);
        if ($validFromDefs === []) {
            $schema->columnDefinitions[] = new ColumnDefinition('valid_from', ColumnDataType::ValidFrom);
        }
        $validUntilDefs = ColumnDefArray::getColumnDefsForType($schema->columnDefinitions, ColumnDataType::ValidUntil);
        if ($validUntilDefs === []) {
            $schema->columnDefinitions[] = new ColumnDefinition('valid_until', ColumnDataType::ValidUntil);
        }
        parent::__construct($udt, $schema, new NoOpRowValueTranslator(), SupportedSearchCondition::reasonableDefaults(), ColumnDataType::cases(), true);
    }
}