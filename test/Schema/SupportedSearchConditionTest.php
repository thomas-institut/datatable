<?php

namespace ThomasInstitut\DataTable\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ThomasInstitut\DataTable\SearchCondition;

#[CoversClass(SupportedSearchCondition::class)]
class SupportedSearchConditionTest extends TestCase
{
    public function testReasonableDefaultsContainFlatConditionLists(): void
    {
        $supportedConditions = SupportedSearchCondition::reasonableDefaults();

        foreach (array_slice($supportedConditions, 0, 3) as $supportedCondition) {
            $this->assertSame(SearchCondition::cases(), $supportedCondition->conditions);
        }
    }
}