<?php

namespace ThomasInstitut\DataTable\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\DataTable\ReferenceTests\RowValueTranslatorReferenceTestCase;

#[CoversClass(StringValuesDbRowValueTranslator::class)]
class StringValuesDbRowValueTranslatorTest extends RowValueTranslatorReferenceTestCase
{
    private StringValuesDbRowValueTranslator $translator;

    public function getRowValueTranslator(): StringValuesDbRowValueTranslator
    {
        return $this->translator;
    }

    protected function setUp(): void
    {
        $this->translator = new StringValuesDbRowValueTranslator();
    }

    #[DataProvider('invalidOptionsProvider')]
    public function testInvalidOptionsAreRejected(array $optionValues, string $expectedMessage): void
    {
        $options = new StringValuesDbRowValueTranslatorOptions();
        foreach ($optionValues as $option => $value) {
            $options->$option = $value;
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new StringValuesDbRowValueTranslator($options);
    }

    public static function invalidOptionsProvider(): array
    {
        return [
            'empty literal string prefix' => [
                ['literalStringPrefix' => ''],
                'Literal string prefix must be a non-empty string, "" given',
            ],
            'blank literal string prefix' => [
                ['literalStringPrefix' => ' '],
                'Literal string prefix must be a non-empty string, " " given',
            ],
            'empty database null value' => [
                ['dbNullValue' => ''],
                'Null value must be a non-empty string or one of [ LitVal= ], "" given',
            ],
            'database null value equals literal prefix' => [
                ['dbNullValue' => 'LitVal='],
                'Null value must be a non-empty string or one of [ LitVal= ], "LitVal=" given',
            ],
            'empty false value' => [
                ['falseValue' => ''],
                'False value must be a non-empty string or one of [ LitVal=, ___NULL___ ], "" given',
            ],
            'false value equals database null value' => [
                ['dbNullValue' => 'NULL', 'falseValue' => 'NULL'],
                'False value must be a non-empty string or one of [ LitVal=, NULL ], "NULL" given',
            ],
            'false value equals literal prefix' => [
                ['falseValue' => 'LitVal='],
                'False value must be a non-empty string or one of [ LitVal=, ___NULL___ ], "LitVal=" given',
            ],
            'empty true value' => [
                ['trueValue' => ''],
                'True value must be a non-empty string or one of [ LitVal=, ___NULL___, 0 ], "" given',
            ],
            'true value equals database null value' => [
                ['dbNullValue' => 'NULL', 'trueValue' => 'NULL'],
                'True value must be a non-empty string or one of [ LitVal=, NULL, 0 ], "NULL" given',
            ],
            'true value equals false value' => [
                ['falseValue' => 'FALSE', 'trueValue' => 'FALSE'],
                'True value must be a non-empty string or one of [ LitVal=, ___NULL___, FALSE ], "FALSE" given',
            ],
            'true value equals literal prefix' => [
                ['trueValue' => 'LitVal='],
                'True value must be a non-empty string or one of [ LitVal=, ___NULL___, 0 ], "LitVal=" given',
            ],
        ];
    }

    #[DataProvider('typesProvider')]
    public function testConfiguredNullValueRoundTripsToNull(ColumnDataType $type): void
    {
        $translator = new StringValuesDbRowValueTranslator();

        $this->assertNull(
            $translator->dbValueToRowValue(
                $translator->rowValueToDbValue(null, $type),
                ColumnDataType::Any,
            ),
        );
    }

    public static function typesProvider(): array
    {
        return [
            [ColumnDataType::Any],
            [ColumnDataType::Text],
            [ColumnDataType::VarChar],
            [ColumnDataType::Boolean],
            [ColumnDataType::Integer],
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    #[DataProvider('problematicStringsProvider')]
    public function testProblematicStringsPassRoundTrip(
        ColumnDataType $type, string $stringValue
    ): void
    {
        $options = new StringValuesDbRowValueTranslatorOptions();
        $options->dbNullValue = 'NULL';
        $options->literalStringPrefix = 'Start:';
        $translator = new StringValuesDbRowValueTranslator($options);

        $this->assertSame(
            $stringValue,
            $translator->dbValueToRowValue(
                $translator->rowValueToDbValue($stringValue, $type),
                $type,
            ),
        );
    }

    public static function problematicStringsProvider(): array
    {
        return [
            'varchar with null' => [ColumnDataType::VarChar, 'NULL'],
            'text with null' => [ColumnDataType::Text, 'NULL'],
            'text with literal prefix' => [ColumnDataType::Text, 'Start:Today'],
            'varchar with literal prefix' => [ColumnDataType::VarChar, 'Start:Tomorrow'],
        ];
    }


    public function testAnyRowValueIsSerialized(): void
    {
        $value = ['name' => 'Ada', 'roles' => ['admin', 'editor']];

        $this->assertSame(
            serialize($value),
            $this->translator->rowValueToDbValue($value, ColumnDataType::Any),
        );
    }

    #[DataProvider('integerRowValueProvider')]
    public function testIntegerAndIdRowValuesAreConvertedToStrings(
        ColumnDataType $type,
        int            $value,
    ): void
    {
        $this->assertSame(
            (string)$value,
            $this->translator->rowValueToDbValue($value, $type),
        );
    }

    public static function integerRowValueProvider(): array
    {
        return [
            'integer' => [ColumnDataType::Integer, -12],
            'id' => [ColumnDataType::Id, 42],
        ];
    }

    #[DataProvider('booleanRowValueProvider')]
    public function testBooleanRowValuesAreConvertedToDatabaseStrings(bool $value, string $expected): void
    {
        $this->assertSame(
            $expected,
            $this->translator->rowValueToDbValue($value, ColumnDataType::Boolean),
        );
    }

    public static function booleanRowValueProvider(): array
    {
        $defaultOptions = new StringValuesDbRowValueTranslatorOptions();
        return [
            'true' => [true, $defaultOptions->trueValue],
            'false' => [false, $defaultOptions->falseValue],
        ];
    }

    #[DataProvider('unchangedRowValueProvider')]
    public function testNonSpecialRowValuesAreReturnedUnchanged(mixed $value, ColumnDataType $type): void
    {
        $this->assertSame($value, $this->translator->rowValueToDbValue($value, $type));
    }

    public static function unchangedRowValueProvider(): array
    {
        return [
            'varchar' => ['name', ColumnDataType::VarChar],
            'text' => ['description', ColumnDataType::Text],
        ];
    }

    public function testAnyDatabaseValueIsUnserialized(): void
    {
        $value = ['name' => 'Ada', 'roles' => ['admin', 'editor']];

        $this->assertSame(
            $value,
            $this->translator->dbValueToRowValue(serialize($value), ColumnDataType::Any),
        );
    }

    #[DataProvider('integerDatabaseValueProvider')]
    public function testIntegerAndIdDatabaseValuesAreConvertedToIntegers(
        ColumnDataType $type,
        string         $value,
        int            $expected,
    ): void
    {
        $this->assertSame(
            $expected,
            $this->translator->dbValueToRowValue($value, $type),
        );
    }

    public static function integerDatabaseValueProvider(): array
    {
        return [
            'integer' => [ColumnDataType::Integer, '-12', -12],
            'id' => [ColumnDataType::Id, '42', 42],
        ];
    }

    #[DataProvider('booleanDatabaseValueProvider')]
    public function testBooleanDatabaseValuesOnlyTreatOneAsTrue(string $value, bool $expected): void
    {
        $this->assertSame(
            $expected,
            $this->translator->dbValueToRowValue($value, ColumnDataType::Boolean),
        );
    }

    public static function booleanDatabaseValueProvider(): array
    {
        return [
            'one' => ['1', true],
            'zero' => ['0', false],
            'other string' => ['true', false],
        ];
    }

    #[DataProvider('unchangedDatabaseValueProvider')]
    public function testNonSpecialDatabaseValuesAreReturnedUnchanged(mixed $value, ColumnDataType $type): void
    {
        $this->assertSame($value, $this->translator->dbValueToRowValue($value, $type));
    }

    public static function unchangedDatabaseValueProvider(): array
    {
        return [
            'varchar' => ['name', ColumnDataType::VarChar],
            'text' => ['description', ColumnDataType::Text],
        ];
    }
}