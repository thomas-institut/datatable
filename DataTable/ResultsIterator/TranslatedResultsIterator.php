<?php

namespace ThomasInstitut\DataTable\ResultsIterator;

use ThomasInstitut\DataTable\Schema\GenericRowTranslator;

class TranslatedResultsIterator implements ResultsIterator
{

    public function __construct(private readonly ResultsIterator $resultsIterator, private readonly GenericRowTranslator $rowTranslator)
    {
    }

    /**
     * @inheritDoc
     */
    public function next(): void
    {
        $this->resultsIterator->next();
    }

    /**
     * @inheritDoc
     */
    public function valid(): bool
    {
        return $this->resultsIterator->valid();
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        $this->resultsIterator->rewind();
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return $this->resultsIterator->count();
    }

    /**
     * @inheritDoc
     */
    public function key(): int
    {
        return $this->resultsIterator->key();
    }

    /**
     * @inheritDoc
     */
    public function current(): ?array
    {
        return $this->rowTranslator->dbRowToOutputRow($this->resultsIterator->current());
    }

    /**
     * @inheritDoc
     */
    public function getFirst(): ?array
    {
        return $this->rowTranslator->dbRowToOutputRow($this->resultsIterator->getFirst());
    }
}