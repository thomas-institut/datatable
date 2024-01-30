<?php

namespace ThomasInstitut\DataTable;

use Iterator;
use PDO;
use PDOStatement;

class PdoUniqueIdsIterator implements Iterator
{

    private PDOStatement $statement;
    private Iterator $source;
    private int $currentKey;

    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
        $this->statement->setFetchMode(PDO::FETCH_NUM);
        $this->source = $statement->getIterator();
        $this->currentKey = 0;
    }

    private function getValueFromResultRow(array $row) : int {
        return intval($row[0]);
    }

    public function current(): ?int
    {
        $current = $this->source->current();
        return isset($current) ? $this->getValueFromResultRow($current) : null;

    }

    public function next(): void
    {
        $this->source->next();
        $this->currentKey++;
    }

    public function key(): int
    {
        return $this->currentKey;
    }

    public function valid(): bool
    {
        return $this->source->valid();
    }

    public function rewind(): void
    {
        $this->source->rewind();
        $this->currentKey = 0;
    }
}