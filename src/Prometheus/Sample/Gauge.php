<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Sample;

class Gauge extends Sample
{
    private float|int $value = 0;

    /**
     * Get gauge value.
     */
    public function getValue(): float|int
    {
        return $this->value;
    }

    /**
     * Set gauge value.
     */
    public function set(float|int $value): static
    {
        $this->value = $value;

        $this->updateMeasuredAt();

        return $this;
    }
}
