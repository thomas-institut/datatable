<?php

namespace ThomasInstitut\DataTable\UnitemporalConsistency;

class UnitemporalConsistencyChecker
{

    /**
     * @param array<int, array<string, mixed>> $rowHistory
     */
    static public function getConsistencyIssues(int $id, array $rowHistory, string $validFromColumn, string $validUntilColumn): array
    {
        $issues = [];
        $previousVersion = null;
        foreach ($rowHistory as $version) {
            if ($version[$validUntilColumn] < $version[$validFromColumn]) {
                $issues[] = new ConsistencyIssue(
                    $id, 
                    IssueType::Error, 
                    IssueCode::InvalidTimeRange, 
                    "validUntil " . $version[$validUntilColumn] . " < validFrom " . $version[$validFromColumn]
                );
            }
            if ($version[$validUntilColumn] === $version[$validFromColumn]) {
                $issues[] = new ConsistencyIssue(
                    $id,
                    IssueType::Warning,
                    IssueCode::ZeroTimeRange,
                    "validUntil " . $version[$validUntilColumn] . " = validFrom " . $version[$validFromColumn]
                );
            }
            if (!is_null($previousVersion)) {
                if ($version[$validFromColumn] < $previousVersion[$validUntilColumn]) {
                    $issues[] = new ConsistencyIssue($id,
                        IssueType::Error,
                        IssueCode::OverlappingVersions,
                        "validFrom " . $version[$validFromColumn] . " < previous version validUntil " . $previousVersion[$validUntilColumn]
                    );
                }
                if ($version[$validFromColumn] > $previousVersion[$validUntilColumn]) {
                    $issues[] = new ConsistencyIssue(
                        $id, IssueType::Info,
                        IssueCode::Gap,
                        "validFrom " . $version[$validFromColumn] . " > previous version validUntil " . $previousVersion[$validUntilColumn]
                    );
                }
            }
            $previousVersion = $version;
        }
        return $issues;
    }
}