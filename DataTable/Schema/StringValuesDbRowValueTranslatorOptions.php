<?php

namespace ThomasInstitut\DataTable\Schema;

final class StringValuesDbRowValueTranslatorOptions
{
    public string $dbNullValue = '___NULL___';
    public string $literalStringPrefix = 'LitVal=';
    public string $trueValue = '1';
    public string $falseValue = '0';
}