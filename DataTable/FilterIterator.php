<?php

namespace ThomasInstitut\DataTable;

use Iterator;

class FilterIterator implements DataTableResultsIterator
{

    private DataTableResultsIterator $source;

    /**
     * @var callable
     */
    private $filter;
    private int $currentKey;

    public function __construct(DataTableResultsIterator $source, callable $filterFunction)
    {
        $this->source = $source;
        $this->filter = $filterFunction;
        $this->currentKey = 0;
    }

    public function current(): mixed
    {
        return $this->source->current();
    }

    public function next(): void
    {
        $this->source->next();
        $this->forward();
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
        $this->forward();
        $this->currentKey = 0;
    }

    /**
     * Advances the source iterator until the next element that passes the filter
     * @return void
     */
    private function forward() : void {
        $filter = $this->filter;
        while($this->source->current() !== null && !$filter($this->source->current())) {
            $this->source->next();
        }
    }

    public function count(): int
    {
        return $this->source->count();
    }

    public function getFirst(): array
    {
        return $this->source->getFirst();
    }
}