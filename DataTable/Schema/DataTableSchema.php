<?php

namespace ThomasInstitut\DataTable\Schema;

/**
 * Class encapsulating all parameters of a DataTable schema
 *
 */
class DataTableSchema
{

    /**
     * @param array<ColumnDefinition> $columnDefinitions
     */
    public function __construct(public array $columnDefinitions)
    {
    }

}