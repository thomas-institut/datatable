<?php

declare(strict_types=1);

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

    public static function allColumnDataTypeValueProvider(): \Iterator
    {
        yield 'any' => [ColumnDataType::Serializable, ['name' => 'Ada']];
        yield 'varchar' => [ColumnDataType::VarChar, 'name'];
        yield 'text' => [ColumnDataType::Text, 'description'];
        yield 'integer' => [ColumnDataType::Integer, -12];
        yield 'boolean' => [ColumnDataType::Boolean, true];
        yield 'time' => [ColumnDataType::TimeString, TimeString::now()];
        yield 'id' => [ColumnDataType::Id, 42];
    }
}
