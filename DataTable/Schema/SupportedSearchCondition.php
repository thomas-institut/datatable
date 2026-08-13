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

    public static function reasonableDefaults(): array
    {
        return [
            new self(ColumnDataType::Text, [SearchCondition::cases()]),
            new self(ColumnDataType::VarChar, [SearchCondition::cases()]),
            new self(ColumnDataType::Integer, [SearchCondition::cases()]),
            new self(ColumnDataType::Boolean, [SearchCondition::Equals, SearchCondition::NotEquals]),
        ];
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