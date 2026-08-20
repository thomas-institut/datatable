<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable\UnitemporalConsistency;

enum IssueCode: int
{
    case InvalidTimeRange = 100;
    case ZeroTimeRange = 101;
    case OverlappingVersions = 102;
    case Gap = 103;

}
