<?php

namespace ThomasInstitut\DataTable;

use ArrayIterator;

class ArrayDataTableResultsIterator implements DataTableResultsIterator
{

    private ArrayIterator $arrayIterator;
    private int $count;
    /**
     * @var mixed|null
     */
    private mixed $first;

    public function __construct(array $results)
    {
        $this->arrayIterator = new ArrayIterator($results);
        $this->count = count($results);
        $this->first = $results[0] ?? null;
    }

    public function current(): mixed
    {
       return $this->arrayIterator->current();
    }

    public function next(): void
    {
        $this->arrayIterator->next();
    }

    public function key(): string|int|null
    {
        return $this->arrayIterator->key();
    }

    public function valid(): bool
    {
        return $this->arrayIterator->valid();
    }

    public function rewind(): void
    {
       $this->arrayIterator->rewind();
    }

    public function count(): int
    {
        return $this->count;
    }

    public function getFirst(): array
    {
        return $this->first;
    }
}