<?php

namespace ThomasInstitut\DataTable\ReferenceTests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ThomasInstitut\DataTable\Schema\ColumnDataType;
use ThomasInstitut\DataTable\Schema\RowValueTranslator;
use ThomasInstitut\TimeString\TimeString;

abstract class RowValueTranslatorReferenceTestCase extends TestCase
{
    abstract public function getRowValueTranslator(): RowValueTranslator;

    #[DataProvider('allColumnDataTypeValueProvider')]
    public function testAllColumnDataTypesRoundTrip(ColumnDataType $type, mixed $value): void
    {
        $translator = $this->getRowValueTranslator();

        $this->assertSame(
            $value,
            $translator->dbValueToRowValue(
                $translator->rowValueToDbValue($value, $type),
                $type,
            ),
        );
    }

    public static function allColumnDataTypeValueProvider(): array
    {
        return [
            'any' => [ColumnDataType::Serializable, ['name' => 'Ada']],
            'varchar' => [ColumnDataType::VarChar, 'name'],
            'text' => [ColumnDataType::Text, 'description'],
            'integer' => [ColumnDataType::Integer, -12],
            'boolean' => [ColumnDataType::Boolean, true],
            'time' => [ColumnDataType::TimeString, TimeString::now()],
            'id' => [ColumnDataType::Id, 42],
        ];
    }
}