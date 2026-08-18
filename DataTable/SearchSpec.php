<?php

namespace ThomasInstitut\DataTable;

class SearchSpec
{
    public function __construct(public string $column, public SearchCondition $condition, public mixed $value)
    {
    }

}
