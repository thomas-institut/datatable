<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable\Schema;

use ThomasInstitut\DataTable\ReferenceTests\RowValueTranslatorReferenceTestCase;

final class NoOpRowValueTranslatorTest extends RowValueTranslatorReferenceTestCase
{
    public function getRowValueTranslator(): RowValueTranslator
    {
        return new NoOpRowValueTranslator();
    }
}
