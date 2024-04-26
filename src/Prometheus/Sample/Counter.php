<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Sample;

class Counter extends Sample
{
    private int $value = 0;

    /**
     * Get counter value.
     */
    public function getValue(): int
    {
        return $this->value;
    }

    /**
     * Increment current counter value.
     */
    public function increment(int $value = 1): static
    {
        $this->value += $value;

        $this->updateMeasuredAt();

        return $this;
    }
}
