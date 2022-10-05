<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Store;

class TraceQueryResult implements \Countable, \IteratorAggregate
{
    private iterable $values;
    private int $count;

    public function __construct(iterable $values, int $count)
    {
        $this->values = $values;
        $this->count = $count;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): \Iterator
    {
        return (fn () => yield from $this->values)();
    }
}
