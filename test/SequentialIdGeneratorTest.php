<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ThomasInstitut\DataTable\IdGenerator\SequentialIdGenerator;

#[CoversClass(SequentialIdGenerator::class)]
final class SequentialIdGeneratorTest extends TestCase
{
    public function testGetOneUnusedIdReturnsTheIdAfterTheMaximum(): void
    {
        $dataTable = $this->createMock(DataTable::class);
        $dataTable->expects($this->once())
            ->method('getMaxId')
            ->willReturn(41);

        $generator = new SequentialIdGenerator();

        $this->assertSame(42, $generator->getOneUnusedId($dataTable));
    }

    public function testGetOneUnusedIdReturnsOneForAnEmptyTable(): void
    {
        $dataTable = $this->createMock(DataTable::class);
        $dataTable->expects($this->once())
            ->method('getMaxId')
            ->willReturn(0);

        $generator = new SequentialIdGenerator();

        $this->assertSame(1, $generator->getOneUnusedId($dataTable));
    }
}
