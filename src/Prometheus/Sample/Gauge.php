<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Sample;

class Gauge extends Sample
{
    private float $value = 0.0;

    /**
     * Get gauge value.
     */
    public function getValue(): float
    {
        return $this->value;
    }

    /**
     * Set gauge value.
     */
    public function set(float $value): static
    {
        $this->value = $value;

        $this->updateMeasuredAt();

        return $this;
    }
}
