<?php

namespace ThomasInstitut\DataTable;

enum SearchCondition : int
{
    case Equals = 0;
    case NotEquals = 1;
    case LessThan = 2;
    case LessThanOrEquals = 3;
    case GreaterThan = 4;
    case GreaterThanOrEquals = 5;

}
