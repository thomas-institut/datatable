<?php

namespace ThomasInstitut\DataTable\Schema;

use ThomasInstitut\DataTable\ReferenceTests\RowValueTranslatorReferenceTestCase;

class NoOpRowValueTranslatorTest extends RowValueTranslatorReferenceTestCase
{
    public function getRowValueTranslator(): RowValueTranslator
    {
        return new NoOpRowValueTranslator();
    }
}