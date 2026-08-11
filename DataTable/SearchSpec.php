<?php

namespace ThomasInstitut\DataTable;

enum SearchSpec : string
{

    case Column = 'column';
    case Value = 'value';
    case Condition = 'condition';
}
