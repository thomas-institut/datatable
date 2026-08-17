<?php

namespace ThomasInstitut\DataTable\UnitemporalConsistency;

enum IssueType: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';

}
