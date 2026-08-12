<?php

namespace ThomasInstitut\DataTable;

enum SearchCondition : string
{
    case Equals = 'eq';
    case NotEquals = 'neq';
    case LessThan = 'lt';
    case LessThanOrEquals = 'lte';
    case GreaterThan = 'gt';
    case GreaterThanOrEquals = 'gte';

}
