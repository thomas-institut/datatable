<?php

namespace ThomasInstitut\DataTable;

use ArrayIterator;

class DataTableResultsArrayIterator implements DataTableResultsIterator
{
    private int $count;
    /**
     * @var mixed|null
     */
    private mixed $first;
    private int $currentKey;
    private array $theArray;

    public function __construct(array $results)
    {
        $this->theArray = array_values($results);
        $this->count = count($results);
        $this->first = $this->theArray[0] ?? null;
        $this->currentKey = 0;
    }

    public function current(): ?array
    {
       return $this->theArray[$this->currentKey] ?? null;
    }

    public function next(): void
    {
        $this->currentKey++;
    }

    public function key(): int
    {
        return $this->currentKey;
    }

    public function valid(): bool
    {
        return isset($this->theArray[$this->currentKey]);
    }

    public function rewind(): void
    {
       $this->currentKey = 0;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function getFirst(): ?array
    {
        return $this->first;
    }
}