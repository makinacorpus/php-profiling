<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Sample;

class HistogramItem
{
    public readonly \DateTimeImmutable $measuredAt; 

    public function __construct(
        public readonly float $value,
    ) {
        $this->measuredAt = new \DateTimeImmutable();
    }
}
