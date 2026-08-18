<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;
use ThomasInstitut\DataTable\ReferenceTests\RowValueTranslatorReferenceTestCase;

#[CoversClass(StringValuesDbRowValueTranslator::class)]
final class StringValuesDbRowValueTranslatorTest extends RowValueTranslatorReferenceTestCase
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

    public static function invalidOptionsProvider(): \Iterator
    {
        yield 'empty literal string prefix' => [
            ['literalStringPrefix' => ''],
            'Literal string prefix must be a non-empty string, "" given',
        ];
        yield 'blank literal string prefix' => [
            ['literalStringPrefix' => ' '],
            'Literal string prefix must be a non-empty string, " " given',
        ];
        yield 'empty database null value' => [
            ['dbNullValue' => ''],
            'Null value must be a non-empty string or one of [ LitVal= ], "" given',
        ];
        yield 'database null value equals literal prefix' => [
            ['dbNullValue' => 'LitVal='],
            'Null value must be a non-empty string or one of [ LitVal= ], "LitVal=" given',
        ];
        yield 'empty false value' => [
            ['falseValue' => ''],
            'False value must be a non-empty string or one of [ LitVal=, ___NULL___ ], "" given',
        ];
        yield 'false value equals database null value' => [
            ['dbNullValue' => 'NULL', 'falseValue' => 'NULL'],
            'False value must be a non-empty string or one of [ LitVal=, NULL ], "NULL" given',
        ];
        yield 'false value equals literal prefix' => [
            ['falseValue' => 'LitVal='],
            'False value must be a non-empty string or one of [ LitVal=, ___NULL___ ], "LitVal=" given',
        ];
        yield 'empty true value' => [
            ['trueValue' => ''],
            'True value must be a non-empty string or one of [ LitVal=, ___NULL___, 0 ], "" given',
        ];
        yield 'true value equals database null value' => [
            ['dbNullValue' => 'NULL', 'trueValue' => 'NULL'],
            'True value must be a non-empty string or one of [ LitVal=, NULL, 0 ], "NULL" given',
        ];
        yield 'true value equals false value' => [
            ['falseValue' => 'FALSE', 'trueValue' => 'FALSE'],
            'True value must be a non-empty string or one of [ LitVal=, ___NULL___, FALSE ], "FALSE" given',
        ];
        yield 'true value equals literal prefix' => [
            ['trueValue' => 'LitVal='],
            'True value must be a non-empty string or one of [ LitVal=, ___NULL___, 0 ], "LitVal=" given',
        ];
    }

    #[DataProvider('typesProvider')]
    public function testConfiguredNullValueRoundTripsToNull(ColumnDataType $type): void
    {
        $translator = new StringValuesDbRowValueTranslator();

        $this->assertNull(
            $translator->dbValueToRowValue(
                $translator->rowValueToDbValue(null, $type),
                ColumnDataType::Serializable,
            ),
        );
    }

    public static function typesProvider(): \Iterator
    {
        yield [ColumnDataType::Serializable];
        yield [ColumnDataType::Text];
        yield [ColumnDataType::VarChar];
        yield [ColumnDataType::Boolean];
        yield [ColumnDataType::Integer];
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

    public static function problematicStringsProvider(): \Iterator
    {
        yield 'varchar with null' => [ColumnDataType::VarChar, 'NULL'];
        yield 'text with null' => [ColumnDataType::Text, 'NULL'];
        yield 'text with literal prefix' => [ColumnDataType::Text, 'Start:Today'];
        yield 'varchar with literal prefix' => [ColumnDataType::VarChar, 'Start:Tomorrow'];
    }


    public function testAnyRowValueIsSerialized(): void
    {
        $value = ['name' => 'Ada', 'roles' => ['admin', 'editor']];

        $this->assertSame(
            serialize($value),
            $this->translator->rowValueToDbValue($value, ColumnDataType::Serializable),
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

    public static function integerRowValueProvider(): \Iterator
    {
        yield 'integer' => [ColumnDataType::Integer, -12];
        yield 'id' => [ColumnDataType::Id, 42];
    }

    #[DataProvider('booleanRowValueProvider')]
    public function testBooleanRowValuesAreConvertedToDatabaseStrings(bool $value, string $expected): void
    {
        $this->assertSame(
            $expected,
            $this->translator->rowValueToDbValue($value, ColumnDataType::Boolean),
        );
    }

    public static function booleanRowValueProvider(): \Iterator
    {
        $defaultOptions = new StringValuesDbRowValueTranslatorOptions();
        yield 'true' => [true, $defaultOptions->trueValue];
        yield 'false' => [false, $defaultOptions->falseValue];
    }

    #[DataProvider('unchangedRowValueProvider')]
    public function testNonSpecialRowValuesAreReturnedUnchanged(mixed $value, ColumnDataType $type): void
    {
        $this->assertSame($value, $this->translator->rowValueToDbValue($value, $type));
    }

    public static function unchangedRowValueProvider(): \Iterator
    {
        yield 'varchar' => ['name', ColumnDataType::VarChar];
        yield 'text' => ['description', ColumnDataType::Text];
    }

    public function testAnyDatabaseValueIsUnserialized(): void
    {
        $value = ['name' => 'Ada', 'roles' => ['admin', 'editor']];

        $this->assertSame(
            $value,
            $this->translator->dbValueToRowValue(serialize($value), ColumnDataType::Serializable),
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

    public static function integerDatabaseValueProvider(): \Iterator
    {
        yield 'integer' => [ColumnDataType::Integer, '-12', -12];
        yield 'id' => [ColumnDataType::Id, '42', 42];
    }

    #[DataProvider('booleanDatabaseValueProvider')]
    public function testBooleanDatabaseValuesOnlyTreatOneAsTrue(string $value, bool $expected): void
    {
        $this->assertSame(
            $expected,
            $this->translator->dbValueToRowValue($value, ColumnDataType::Boolean),
        );
    }

    public static function booleanDatabaseValueProvider(): \Iterator
    {
        yield 'one' => ['1', true];
        yield 'zero' => ['0', false];
        yield 'other string' => ['true', false];
    }

    #[DataProvider('unchangedDatabaseValueProvider')]
    public function testNonSpecialDatabaseValuesAreReturnedUnchanged(mixed $value, ColumnDataType $type): void
    {
        $this->assertSame($value, $this->translator->dbValueToRowValue($value, $type));
    }

    public static function unchangedDatabaseValueProvider(): \Iterator
    {
        yield 'varchar' => ['name', ColumnDataType::VarChar];
        yield 'text' => ['description', ColumnDataType::Text];
    }
}
