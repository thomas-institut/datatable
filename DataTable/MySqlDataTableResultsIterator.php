<?php

namespace ThomasInstitut\DataTable;

use Iterator;
use PDOStatement;

/**
 * Wrapper around a PDOStatement iterator:
 *   * enforces the id column the result rows to be of type integer.
 *   * reports the row id as the key to each result
 */
class MySqlDataTableResultsIterator implements DataTableResultsIterator
{

    private string $idColumnName;
    private Iterator $source;
    private PDOStatement $statement;
    /**
     * @var null
     */
    private ?array $first;

    public function __construct(PDOStatement $statement, string $idColumnName)
    {
        $this->statement = $statement;
        $this->source = $statement->getIterator();
        $this->idColumnName = $idColumnName;
        $this->first = null;
    }

    private function forceIntId(array $row) : array {
        $row[$this->idColumnName] = intval($row[$this->idColumnName]);
        return $row;
    }

    public function current(): array
    {
        return $this->forceIntId($this->source->current());

    }

    public function next(): void
    {
       $this->source->next();
    }

    public function key(): int
    {
        return $this->source->current()[$this->idColumnName];
    }

    public function valid(): bool
    {
        return $this->source->valid();
    }

    public function rewind(): void
    {
        $this->source->rewind();
    }

    public function count(): int
    {
        return $this->statement->rowCount();
    }

    public function getFirst(): array
    {
        if ($this->first === null) {
            $this->rewind();
            $this->first = $this->current();
        }
        return $this->first;
    }
}