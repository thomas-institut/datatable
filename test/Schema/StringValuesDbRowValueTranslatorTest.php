<?php

namespace ThomasInstitut\DataTable\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
        int $value,
    ): void {
        $this->assertSame(
            (string) $value,
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
        return [
            'true' => [true, '1'],
            'false' => [false, '0'],
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
        string $value,
        int $expected,
    ): void {
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