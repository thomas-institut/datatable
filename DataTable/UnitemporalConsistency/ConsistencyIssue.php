<?php

namespace ThomasInstitut\DataTable\UnitemporalConsistency;

final readonly class ConsistencyIssue
{
    /**
     * @param int $id Id to which the issue belongs
     * @param IssueType $type
     * @param IssueCode $code
     * @param string $message
     */
    public function __construct(public int       $id,
                                public IssueType $type,
                                public IssueCode $code,
                                public string    $message)
    {
    }
}