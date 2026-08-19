<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ThomasInstitut\DataTable\DataTable;
use ThomasInstitut\DataTable\Exception\InvalidColumnDefinitionsArray;
use ThomasInstitut\DataTable\Exception\InvalidRow;
use ThomasInstitut\DataTable\Exception\InvalidSearchSpec;
use ThomasInstitut\DataTable\SearchCondition;
use ThomasInstitut\DataTable\SearchSpec;
use ThomasInstitut\DataTable\SearchType;

#[CoversClass(SearchSpecTranslator::class)]
final class SearchSpecTranslatorTest extends TestCase
{
    #[DataProvider('searchTypeProvider')]
    public function testSearchTypesAreTranslated(SearchType $searchType, int $expectedSearchType): void
    {
        $this->assertSame(
            $expectedSearchType,
            SearchSpecTranslator::searchTypeToDataTableSearchType($searchType),
        );
    }

    public static function searchTypeProvider(): \Iterator
    {
        yield 'and' => [SearchType::And, DataTable::SEARCH_AND];
        yield 'or' => [SearchType::Or, DataTable::SEARCH_OR];
    }

    #[DataProvider('searchConditionProvider')]
    public function testSearchConditionsAreTranslated(
        SearchCondition $searchCondition,
        int             $expectedCondition,
    ): void {
        $this->assertSame(
            $expectedCondition,
            SearchSpecTranslator::searchConditionToDataTableCondition($searchCondition),
        );
    }

    public static function searchConditionProvider(): \Iterator
    {
        yield 'equals' => [SearchCondition::Equals, DataTable::COND_EQUAL_TO];
        yield 'not equals' => [SearchCondition::NotEquals, DataTable::COND_NOT_EQUAL_TO];
        yield 'less than' => [SearchCondition::LessThan, DataTable::COND_LESS_THAN];
        yield 'less than or equals' => [SearchCondition::LessThanOrEquals, DataTable::COND_LESS_OR_EQUAL_TO];
        yield 'greater than' => [SearchCondition::GreaterThan, DataTable::COND_GREATER_THAN];
        yield 'greater than or equals' => [SearchCondition::GreaterThanOrEquals, DataTable::COND_GREATER_OR_EQUAL_TO];
    }

    /**
     * @throws InvalidSearchSpec
     * @throws InvalidRow
     * @throws InvalidColumnDefinitionsArray
     */
    public function testSearchSpecIsTranslatedToDatabaseColumnAndValue(): void
    {
        $columnDefinitions = [
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition('enabled', ColumnDataType::Boolean))->withDbColumn('is_enabled'),
        ];
        $rowTranslator = new GenericRowTranslator(new StringValuesDbRowValueTranslator(), $columnDefinitions);

        $translatedSpec = SearchSpecTranslator::toDataTableSearchSpec(
            new SearchSpec('enabled', SearchCondition::Equals, true),
            $columnDefinitions,
            $rowTranslator,
            SupportedSearchCondition::allConditionsSupported(),
        );

        $this->assertSame([
            'column' => 'is_enabled',
            'condition' => DataTable::COND_EQUAL_TO,
            'value' => '1',
        ], $translatedSpec);
    }

    /**
     * @throws InvalidSearchSpec
     * @throws InvalidRow
     * @throws InvalidColumnDefinitionsArray
     */
    public function testSearchSpecArrayIsTranslatedInOrder(): void
    {
        $columnDefinitions = [
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition('name', ColumnDataType::Text))
                ->withDbColumn('full_name')
                ->withRequired(true),
            (new ColumnDefinition('age', ColumnDataType::Integer))->withDbColumn('years'),
        ];
        $rowTranslator = new GenericRowTranslator(new StringValuesDbRowValueTranslator(), $columnDefinitions);

        $translatedSpecs = SearchSpecTranslator::toDataTableSearchSpecArray([
            new SearchSpec('name', SearchCondition::Equals, 'Ada'),
            new SearchSpec('age', SearchCondition::GreaterThan, 30),
        ], $columnDefinitions, $rowTranslator, SupportedSearchCondition::allConditionsSupported());

        $this->assertSame([
            [
                'column' => 'full_name',
                'condition' => DataTable::COND_EQUAL_TO,
                'value' => 'Ada',
            ],
            [
                'column' => 'years',
                'condition' => DataTable::COND_GREATER_THAN,
                'value' => '30',
            ],
        ], $translatedSpecs);
    }

    /**
     * @throws InvalidSearchSpec
     * @throws InvalidRow
     * @throws InvalidColumnDefinitionsArray
     */
    public function testNullableNullValueIsValidatedAndTranslated(): void
    {
        $columnDefinitions = [
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition('deletedAt', ColumnDataType::Text))
                ->withDbColumn('deleted_at')
                ->withRequired(true)
                ->withNullable(true),
        ];
        $rowTranslator = new GenericRowTranslator(new StringValuesDbRowValueTranslator(), $columnDefinitions);

        $translatedSpec = SearchSpecTranslator::toDataTableSearchSpec(
            new SearchSpec('deletedAt', SearchCondition::Equals, null),
            $columnDefinitions,
            $rowTranslator,
            SupportedSearchCondition::allConditionsSupported(),
        );

        $this->assertSame([
            'column' => 'deleted_at',
            'condition' => DataTable::COND_EQUAL_TO,
            'value' => '___NULL___',
        ], $translatedSpec);
    }

    /**
     * @throws InvalidRow
     * @throws InvalidColumnDefinitionsArray
     */
    public function testUndefinedColumnThrowsInvalidSearchSpec(): void
    {
        $columnDefinitions = [new ColumnDefinition('id', ColumnDataType::Id)];

        $this->expectException(InvalidSearchSpec::class);
        $this->expectExceptionMessage("Column 'missing' does not exist.");

        SearchSpecTranslator::toDataTableSearchSpec(
            new SearchSpec('missing', SearchCondition::Equals, 'value'),
            $columnDefinitions,
            new GenericRowTranslator(new NoOpRowValueTranslator(), $columnDefinitions),
            SupportedSearchCondition::allConditionsSupported(),
        );
    }

    /**
     * @throws InvalidRow
     * @throws InvalidColumnDefinitionsArray
     */
    #[DataProvider('invalidBooleanConditionProvider')]
    public function testOrderingConditionsAreRejectedForBooleanColumns(SearchCondition $condition): void
    {
        $columnDefinitions = [
            new ColumnDefinition('id', ColumnDataType::Id),
            new ColumnDefinition('enabled', ColumnDataType::Boolean),
        ];

        $this->expectException(InvalidSearchSpec::class);
        $this->expectExceptionMessage("Search condition '$condition->value' is not supported for column 'enabled' of type 'boolean'.");

        $supportedSearchConditions = [
            new SupportedSearchCondition(ColumnDataType::Boolean, [
                SearchCondition::Equals,
                SearchCondition::NotEquals,
            ]),
        ];

        SearchSpecTranslator::toDataTableSearchSpec(
            new SearchSpec('enabled', $condition, true),
            $columnDefinitions,
            new GenericRowTranslator(new NoOpRowValueTranslator(), $columnDefinitions),
            $supportedSearchConditions,
        );
    }

    /**
     * @throws InvalidRow
     * @throws InvalidColumnDefinitionsArray
     */
    #[DataProvider('invalidBooleanConditionProvider')]
    public function testNotDefinedConditionsAreRejected(SearchCondition $condition): void
    {
        $columnDefinitions = [
            new ColumnDefinition('id', ColumnDataType::Id),
            new ColumnDefinition('enabled', ColumnDataType::Boolean),
        ];

        $this->expectException(InvalidSearchSpec::class);
        $this->expectExceptionMessage("Search condition '$condition->value' is not supported for column 'enabled' of type 'boolean'.");

        $supportedSearchConditions = [];

        SearchSpecTranslator::toDataTableSearchSpec(
            new SearchSpec('enabled', $condition, true),
            $columnDefinitions,
            new GenericRowTranslator(new NoOpRowValueTranslator(), $columnDefinitions),
            $supportedSearchConditions,
        );
    }

    public static function invalidBooleanConditionProvider(): \Iterator
    {
        yield 'less than' => [SearchCondition::LessThan];
        yield 'less than or equals' => [SearchCondition::LessThanOrEquals];
        yield 'greater than' => [SearchCondition::GreaterThan];
        yield 'greater than or equals' => [SearchCondition::GreaterThanOrEquals];
    }

    /**
     * @throws InvalidRow
     * @throws InvalidColumnDefinitionsArray
     */
    #[DataProvider('invalidSearchValueProvider')]
    public function testInvalidSearchValueThrowsInvalidSearchSpec(
        ColumnDefinition $columnDefinition,
        mixed            $value,
    ): void {
        $columnDefinitions = [
            new ColumnDefinition('id', ColumnDataType::Id),
            $columnDefinition,
        ];

        $this->expectException(InvalidSearchSpec::class);

        SearchSpecTranslator::toDataTableSearchSpec(
            new SearchSpec($columnDefinition->rowKey, SearchCondition::Equals, $value),
            $columnDefinitions,
            new GenericRowTranslator(new NoOpRowValueTranslator(), $columnDefinitions),
            SupportedSearchCondition::allConditionsSupported(),
        );
    }

    public static function invalidSearchValueProvider(): \Iterator
    {
        yield 'text receives integer' => [
            (new ColumnDefinition('description', ColumnDataType::Text))->withRequired(true),
            123,
        ];
        yield 'varchar exceeds maximum length' => [
            (new ColumnDefinition('name', ColumnDataType::VarChar))->withTypeLength(3),
            'long',
        ];
        yield 'integer receives numeric string' => [new ColumnDefinition('age', ColumnDataType::Integer), '42'];
        yield 'boolean receives integer' => [new ColumnDefinition('enabled', ColumnDataType::Boolean), 1];
        yield 'non-nullable column receives null' => [
            (new ColumnDefinition('value', ColumnDataType::Text))->withRequired(true),
            null,
        ];
    }

    /**
     * @throws InvalidSearchSpec
     */
    public function testInvalidRowFromRowTranslatorIsNotPropagated(): void
    {
        $columnDefinitions = [
            new ColumnDefinition('id', ColumnDataType::Id),
            (new ColumnDefinition('name', ColumnDataType::Text))->withRequired(true),
        ];
        $rowTranslator = $this->createMock(RowTranslator::class);
        $rowTranslator->expects($this->once())
            ->method('inputRowToDb')
            ->with(['name' => 'Ada'], false)
            ->willThrowException(new InvalidRow('Unable to translate row'));

        $this->expectException(InvalidSearchSpec::class);

        SearchSpecTranslator::toDataTableSearchSpec(
            new SearchSpec('name', SearchCondition::Equals, 'Ada'),
            $columnDefinitions,
            $rowTranslator,
            SupportedSearchCondition::allConditionsSupported(),
        );
    }
}
