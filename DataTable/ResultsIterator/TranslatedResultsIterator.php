<?php

namespace ThomasInstitut\DataTable\ResultsIterator;

use ThomasInstitut\DataTable\Schema\GenericRowTranslator;

readonly class TranslatedResultsIterator implements ResultsIterator
{

    public function __construct(private ResultsIterator $resultsIterator, private GenericRowTranslator $rowTranslator)
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
        $current = $this->resultsIterator->current();
        return $current === null ? null : $this->rowTranslator->dbRowToOutputRow($current);
    }

    /**
     * @inheritDoc
     */
    public function getFirst(): ?array
    {
        $first = $this->resultsIterator->getFirst();
        return $first === null ? null : $this->rowTranslator->dbRowToOutputRow($first);
    }
}