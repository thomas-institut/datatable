<?php

namespace ThomasInstitut\DataTable\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ThomasInstitut\DataTable\Exception\InvalidArgumentException;

#[CoversClass(ColumnDefinition::class)]
class ColumnDefinitionTest extends TestCase
{
    #[DataProvider('constructorDefaultsProvider')]
    public function testConstructorSetsPropertiesAndDefaultValue(
        ColumnDataType $type,
        mixed $expectedDefaultValue,
    ): void {
        $columnDefinition = new ColumnDefinition('column', $type);

        $this->assertSame($type, $columnDefinition->type);
        $this->assertSame('column', $columnDefinition->rowKey);
        $this->assertFalse($columnDefinition->nullable);
        $this->assertSame(-1, $columnDefinition->typeLength);
        $this->assertNull($columnDefinition->dbColumn);
        $this->assertFalse($columnDefinition->required);
        $this->assertSame($expectedDefaultValue, $columnDefinition->defaultValue);
    }

    public static function constructorDefaultsProvider(): array
    {
        return [
            'serializable' => [ColumnDataType::Serializable, null],
            'varchar' => [ColumnDataType::VarChar, -1],
            'text' => [ColumnDataType::Text, null],
            'integer' => [ColumnDataType::Integer, -1],
            'boolean' => [ColumnDataType::Boolean, false],
            'id' => [ColumnDataType::Id, -1],
            'time string' => [ColumnDataType::TimeString, '1000-01-01 00:00:00.000000'],
        ];
    }
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

    #[DataProvider('timeStringValueProvider')]
    public function testTimeStringColumnOnlyAcceptsValidTimeStrings(mixed $value, bool $expected): void
    {
        $columnDefinition = new ColumnDefinition('created_at', ColumnDataType::TimeString);

        $this->assertSame(
            $expected,
            ColumnDefinition::valueIsValidForColumn($value, $columnDefinition),
        );
    }

    public static function timeStringValueProvider(): array
    {
        return [
            'valid time string' => ['2024-01-02 03:04:05.123456', true],
            'invalid time string' => ['not a time string', false],
            'integer' => [123, false],
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    #[DataProvider('validDefaultValueProvider')]
    public function testWithDefaultValueAcceptsValuesValidForTheColumnType(
        ColumnDataType $type,
        mixed $defaultValue,
    ): void {
        $columnDefinition = new ColumnDefinition('column', $type);
        if ($type === ColumnDataType::VarChar) {
            $columnDefinition->withTypeLength(10);
        }

        $result = $columnDefinition->withDefaultValue($defaultValue);

        $this->assertSame($columnDefinition, $result);
        $this->assertSame($defaultValue, $columnDefinition->defaultValue);
    }

    public static function validDefaultValueProvider(): array
    {
        return [
            'serializable' => [ColumnDataType::Serializable, ['key' => 'value']],
            'varchar' => [ColumnDataType::VarChar, 'default'],
            'text' => [ColumnDataType::Text, 'default'],
            'integer' => [ColumnDataType::Integer, 42],
            'boolean' => [ColumnDataType::Boolean, true],
            'id' => [ColumnDataType::Id, 42],
            'time string' => [ColumnDataType::TimeString, '2024-01-02 03:04:05.123456'],
        ];
    }

    #[DataProvider('invalidDefaultValueProvider')]
    public function testWithDefaultValueRejectsValuesInvalidForTheColumnType(
        ColumnDataType $type,
        mixed $defaultValue,
        ?int $typeLength = null,
    ): void {
        $columnDefinition = new ColumnDefinition('column', $type);
        if ($typeLength !== null) {
            $columnDefinition->withTypeLength($typeLength);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid default value for column column');

        $columnDefinition->withDefaultValue($defaultValue);
    }

    public static function invalidDefaultValueProvider(): array
    {
        return [
            'varchar too long' => [ColumnDataType::VarChar, '1234', 3],
            'text integer' => [ColumnDataType::Text, 123],
            'integer string' => [ColumnDataType::Integer, '42'],
            'boolean integer' => [ColumnDataType::Boolean, 1],
            'id null' => [ColumnDataType::Id, null],
            'time string invalid' => [ColumnDataType::TimeString, 'not a time string'],
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testWithDefaultValueAcceptsNullForNullableColumn(): void
    {
        $columnDefinition = (new ColumnDefinition('name', ColumnDataType::Text))
            ->withNullable(true);

        $this->assertSame($columnDefinition, $columnDefinition->withDefaultValue(null));
        $this->assertNull($columnDefinition->defaultValue);
    }

    public function testWithDefaultValueRejectsNullForNonNullableColumn(): void
    {
        $columnDefinition = new ColumnDefinition('name', ColumnDataType::Text);

        $this->expectException(InvalidArgumentException::class);

        $columnDefinition->withDefaultValue(null);
    }

    public function testWithDbColumnSetsDatabaseColumnAndReturnsSameDefinition(): void
    {
        $columnDefinition = new ColumnDefinition('name', ColumnDataType::Text);

        $this->assertSame($columnDefinition, $columnDefinition->withDbColumn('full_name'));
        $this->assertSame('full_name', $columnDefinition->dbColumn);
    }

    #[DataProvider('requiredValueProvider')]
    public function testWithRequiredSetsRequiredFlag(
        ColumnDataType $type,
        bool $required,
        bool $expected,
    ): void {
        $columnDefinition = new ColumnDefinition('column', $type);

        $this->assertSame($columnDefinition, $columnDefinition->withRequired($required));
        $this->assertSame($expected, $columnDefinition->required);
    }

    public static function requiredValueProvider(): array
    {
        return [
            'optional integer' => [ColumnDataType::Integer, false, false],
            'required integer' => [ColumnDataType::Integer, true, true],
            'serializable always required' => [ColumnDataType::Serializable, false, true],
            'text always required' => [ColumnDataType::Text, false, true],
        ];
    }

    public function testWithTypeLengthSetsLengthAndReturnsSameDefinition(): void
    {
        $columnDefinition = new ColumnDefinition('name', ColumnDataType::VarChar);

        $this->assertSame($columnDefinition, $columnDefinition->withTypeLength(64));
        $this->assertSame(64, $columnDefinition->typeLength);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testWithNullableSetsNullableFlagResetsDefaultAndReturnsSameDefinition(): void
    {
        $columnDefinition = (new ColumnDefinition('name', ColumnDataType::VarChar))
            ->withTypeLength(10)
            ->withDefaultValue('default');

        $this->assertSame($columnDefinition, $columnDefinition->withNullable(true));
        $this->assertTrue($columnDefinition->nullable);
        $this->assertNull($columnDefinition->defaultValue);

        $columnDefinition->withNullable(false);
        $this->assertFalse($columnDefinition->nullable);
        $this->assertNull($columnDefinition->defaultValue);
    }
}