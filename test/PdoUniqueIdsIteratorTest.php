<?php

namespace ThomasInstitut\DataTable;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdoUniqueIdsIterator::class)]
class PdoUniqueIdsIteratorTest extends TestCase
{
    private function getPdo(): PDO
    {
        $db = MySqlDataTableTest::DB;
        $dsn = "mysql:dbname=$db;host=mysql";
        return new PDO($dsn, 'root', 'root');
    }

    public function testIterator(): void
    {
        $pdo = $this->getPdo();
        $pdo->query("DROP TABLE IF EXISTS test_ids");
        $pdo->query("CREATE TABLE test_ids (id INT)");
        $pdo->query("INSERT INTO test_ids VALUES (10), (20), (30)");

        $stmt = $pdo->query("SELECT id FROM test_ids ORDER BY id ");
        $iterator = new PdoUniqueIdsIterator($stmt);

        $expected = [10, 20, 30];
        $actual = [];
        foreach ($iterator as $id) {
            $actual[] = $id;
        }

        $this->assertEquals($expected, $actual);

        // Test basic methods
        $stmt = $pdo->query("SELECT id FROM test_ids ORDER BY id ");
        $iterator = new PdoUniqueIdsIterator($stmt);
        $this->assertEquals(10, $iterator->current());
        $this->assertEquals(0, $iterator->key());

        $iterator->next();
        $this->assertEquals(20, $iterator->current());
        $this->assertEquals(1, $iterator->key());
        $this->assertTrue($iterator->valid());

        $iterator->next();
        $iterator->next();
        $this->assertFalse($iterator->valid());
        $this->assertNull($iterator->current());
    }
}
