<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Logger;

use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Sample\Summary;

class NullSampleLogger implements SampleLogger
{
    #[\Override]
    public function counter(string $name, array $labelValues, ?int $value = null): Counter
    {
        return new Counter('null', [], []);
    }

    #[\Override]
    public function gauge(string $name, array $labelValues, ?float $value = null): Gauge
    {
        return new Gauge('null', [], []);
    }

    #[\Override]
    public function summary(string $name, array $labelValues, float ...$values): Summary
    {
        return new Summary('null', [], []);
    }

    // #[\Override]
    // public function histogram(string $name, array $labelValues, float ...$values): HistogramSample;

    #[\Override]
    public function flush(): void
    {
    }

    #[\Override]
    public function size(): int
    {
        return 0;
    }
}
