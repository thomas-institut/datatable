<?php

namespace ThomasInstitut\DataTable\UnitemporalConsistency;

final readonly class ConsistencyIssue
{
    /**
     * @param int $id Id to which the issue belongs
     */
    public function __construct(public int       $id,
                                public IssueType $type,
                                public IssueCode $code,
                                public string    $message)
    {
    }
}