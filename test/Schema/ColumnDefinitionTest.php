<?php

namespace ThomasInstitut\DataTable\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColumnDefinition::class)]
class ColumnDefinitionTest extends TestCase
{
    public function testNullableColumnAcceptsNull(): void
    {
        $columnDefinition = (new ColumnDefinition('name', ColumnDataType::Text))
            ->withNullable(true);

        $this->assertTrue(ColumnDefinition::valueIsValidForColumn(null, $columnDefinition));
    }

    public function testNonNullableColumnRejectsNull(): void
    {
        $columnDefinition = new ColumnDefinition('name', ColumnDataType::Text);

        $this->assertFalse(ColumnDefinition::valueIsValidForColumn(null, $columnDefinition));
    }

    public function testIdColumnRejectsNullEvenWhenNullable(): void
    {
        $columnDefinition = (new ColumnDefinition('id', ColumnDataType::Id))
            ->withNullable(true);

        $this->assertFalse(ColumnDefinition::valueIsValidForColumn(null, $columnDefinition));
    }

    #[DataProvider('anyValueProvider')]
    public function testAnyColumnAcceptsAnyNonNullValue(mixed $value): void
    {
        $columnDefinition = new ColumnDefinition('value', ColumnDataType::Serializable);

        $this->assertTrue(ColumnDefinition::valueIsValidForColumn($value, $columnDefinition));
    }

    public static function anyValueProvider(): array
    {
        return [
            'string' => ['value'],
            'integer' => [1],
            'array' => [['value']],
            'object' => [new \stdClass()],
        ];
    }

    #[DataProvider('varcharValueProvider')]
    public function testVarCharOnlyAcceptsStringsWithinMaximumLength(mixed $value, bool $expected): void
    {
        $columnDefinition = (new ColumnDefinition('name', ColumnDataType::VarChar))
            ->withTypeLength(5);

        $this->assertSame(
            $expected,
            ColumnDefinition::valueIsValidForColumn($value, $columnDefinition),
        );
    }

    public static function varcharValueProvider(): array
    {
        return [
            'empty string' => ['', true],
            'maximum length' => ['12345', true],
            'too long' => ['123456', false],
            'integer' => [12345, false],
        ];
    }

    #[DataProvider('textValueProvider')]
    public function testTextOnlyAcceptsStrings(mixed $value, bool $expected): void
    {
        $columnDefinition = new ColumnDefinition('description', ColumnDataType::Text);

        $this->assertSame(
            $expected,
            ColumnDefinition::valueIsValidForColumn($value, $columnDefinition),
        );
    }

    public static function textValueProvider(): array
    {
        return [
            'string' => ['description', true],
            'integer' => [1, false],
            'array' => [['description'], false],
        ];
    }

    #[DataProvider('integerValueProvider')]
    public function testIdAndIntegerColumnsOnlyAcceptIntegers(
        ColumnDataType $type,
        mixed $value,
        bool $expected,
    ): void {
        $columnDefinition = new ColumnDefinition('number', $type);

        $this->assertSame(
            $expected,
            ColumnDefinition::valueIsValidForColumn($value, $columnDefinition),
        );
    }

    public static function integerValueProvider(): array
    {
        return [
            'id integer' => [ColumnDataType::Id, 1, true],
            'regular integer' => [ColumnDataType::Integer, -1, true],
            'string number' => [ColumnDataType::Integer, '1', false],
            'float number' => [ColumnDataType::Id, 1.0, false],
        ];
    }

    #[DataProvider('booleanValueProvider')]
    public function testBooleanColumnOnlyAcceptsBooleans(mixed $value, bool $expected): void
    {
        $columnDefinition = new ColumnDefinition('enabled', ColumnDataType::Boolean);

        $this->assertSame(
            $expected,
            ColumnDefinition::valueIsValidForColumn($value, $columnDefinition),
        );
    }

    public static function booleanValueProvider(): array
    {
        return [
            'true' => [true, true],
            'false' => [false, true],
            'integer one' => [1, false],
            'string true' => ['true', false],
        ];
    }
}