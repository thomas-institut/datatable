<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable;


use ThomasInstitut\DataTable\Schema\DataTableSchema;

final class InMemoryUnitemporalDataTableWithSchemaTest extends ReferenceTests\UnitemporalDataTableWithSchemaReferenceTestCase
{

    /**
     * @inheritDoc
     */
    public function getUnitemporalTestTable(array $columnDefinitions): UnitemporalDataTableWithSchema
    {
        $schema = new DataTableSchema($columnDefinitions);
        return new InMemoryUnitemporalDataTableWithSchema($schema);
    }
}