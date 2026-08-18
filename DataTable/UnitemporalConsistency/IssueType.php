<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable\UnitemporalConsistency;

enum IssueType: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';

}
