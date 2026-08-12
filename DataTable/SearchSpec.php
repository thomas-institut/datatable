<?php

namespace ThomasInstitut\DataTable;

class SearchSpec
{
    public string $column;
    public SearchCondition $condition;
    public mixed $value;


    public function __construct(string $column, SearchCondition $condition, mixed $value)
    {
        $this->column = $column;
        $this->condition = $condition;
        $this->value = $value;
    }

}
