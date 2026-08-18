<?php

namespace ThomasInstitut\DataTable\ResultsIterator;

class ArrayResultsIterator implements ResultsIterator
{
    private readonly int $count;
    /**
     * @var mixed|null
     */
    private readonly mixed $first;
    private int $currentKey = 0;
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $theArray;

    /**
     * @param array<int, array<string, mixed>> $results
     */
    public function __construct(array $results)
    {
        $this->theArray = array_values($results);
        $this->count = count($results);
        $this->first = $this->theArray[0] ?? null;
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

    /**
     * @return array<string, mixed>|null
     */
    public function getFirst(): ?array
    {
        return $this->first;
    }
}