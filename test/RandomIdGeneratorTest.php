<?php

namespace ThomasInstitut\DataTable;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ThomasInstitut\DataTable\IdGenerator\RandomIdGenerator;

#[CoversClass(RandomIdGenerator::class)]
class RandomIdGeneratorTest extends TestCase
{
    #[AllowMockObjectsWithoutExpectations]
    public function testGetOneUnusedId(): void
    {
        $min = 10;
        $max = 20;
        $generator = new RandomIdGenerator($min, $max);

        $dataTable = $this->createMock(DataTable::class);
        $dataTable->method('rowExists')
            ->willReturnCallback(fn($id) => $id === 15);

        $id = $generator->getOneUnusedId($dataTable);

        $this->assertGreaterThanOrEqual($min, $id);
        $this->assertLessThanOrEqual($max, $id);
        $this->assertNotEquals(15, $id);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testMaxAttemptsReached(): void
    {
        $generator = new RandomIdGenerator(1, 1, 10);

        $dataTable = $this->createMock(DataTable::class);
        $dataTable->method('rowExists')->willReturn(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(RandomIdGenerator::ERROR_MAX_ATTEMPTS_REACHED);

        $generator->getOneUnusedId($dataTable);
    }
}
