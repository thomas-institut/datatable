<?php

namespace ThomasInstitut\DataTable\Schema;

use ThomasInstitut\DataTable\SearchCondition;

final class SupportedSearchCondition
{

    /**
     * @param ColumnDataType $type
     * @param array<SearchCondition> $conditions
     * @codeCoverageIgnore
     */
    public function __construct(public ColumnDataType $type, public array $conditions)
    {
    }

    /**
     * Returns an array in which all conditions are supported for all types
     * @return array<SupportedSearchCondition>
     * @codeCoverageIgnore
     */
    public static function allConditionsSupported(): array
    {
        $types = ColumnDataType::cases();
        $conditions = SearchCondition::cases();
        $result = [];
        foreach ($types as $type) {
            $result[] = new self($type, $conditions);
        }
        return $result;
    }
}