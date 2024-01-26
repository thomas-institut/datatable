<?php

namespace ThomasInstitut\DataTable;

use Iterator;
use PDOStatement;

/**
 * Wrapper around a PDOStatement iterator:
 *   * enforces the id column the result rows to be of type integer.
 *   * reports the row id as the key to each result
 */
class DataTableResultsPdoIterator implements DataTableResultsIterator
{

    private string $idColumnName;
    private Iterator $source;
    private PDOStatement $statement;

    private mixed $first;
    private int $currentKey;

    public function __construct(PDOStatement $statement, string $idColumnName)
    {
        $this->statement = $statement;
        $this->source = $statement->getIterator();
        $this->idColumnName = $idColumnName;
        $this->first = null;
        $this->currentKey = 0;
    }

    private function forceIntId(array $row) : array {
        $row[$this->idColumnName] = intval($row[$this->idColumnName]);
        return $row;
    }

    public function current(): ?array
    {
        $current = $this->source->current();
        return isset($current) ? $this->forceIntId($current) : null;

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

    public function count(): int
    {
        return $this->statement->rowCount();
    }

    public function getFirst(): ?array
    {
        if ($this->statement->rowCount() === 0) {
            return null;
        }
        if ($this->first === null) {
            $this->rewind();
            $this->first = $this->forceIntId($this->current());
        }
        return $this->first;
    }
}