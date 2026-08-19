<?php

namespace ThomasInstitut\DataTable;

use Iterator;
use Psr\Log\LoggerInterface;
use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\IdGenerator\IdGenerator;
use ThomasInstitut\DataTable\ResultsIterator\ResultsIterator;
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\ColumnDefArray;
use ThomasInstitut\DataTable\Schema\ColumnDefinition;
use ThomasInstitut\DataTable\Schema\DataTableSchema;
use ThomasInstitut\DataTable\Schema\NoOpRowValueTranslator;
use ThomasInstitut\DataTable\Schema\RowValueTranslator;
use ThomasInstitut\DataTable\UnitemporalDataTableWithSchema;
use Traversable;

class InMemoryUnitemporalDataTableWithSchema extends GenericUnitemporalDataTableWithSchema
{
    public function __construct(DataTableSchema $schema) {
        $udt = new InMemoryUnitemporalDataTable();
        $validFromDefs = ColumnDefArray::getColumnDefsForType($schema->columnDefinitions, ColumnDataType::ValidFrom);
        if (empty($validFromDefs)) {
            $schema->columnDefinitions[] = new ColumnDefinition('valid_from', ColumnDataType::ValidFrom);
        }
        $validUntilDefs = ColumnDefArray::getColumnDefsForType($schema->columnDefinitions, ColumnDataType::ValidUntil);
        if (empty($validUntilDefs)) {
            $schema->columnDefinitions[] = new ColumnDefinition('valid_until', ColumnDataType::ValidUntil);
        }
        parent::__construct($udt, $schema);
    }
}