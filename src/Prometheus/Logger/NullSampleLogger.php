<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Logger;

use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Sample\Summary;
use MakinaCorpus\Profiling\Prometheus\Sample\Histogram;

class NullSampleLogger implements SampleLogger
{
    #[\Override]
    public function counter(string $name, array $labelValues, ?int $value = null): Counter
    {
        return new Counter($name, $labelValues, []);
    }

    #[\Override]
    public function gauge(string $name, array $labelValues, null|float|int $value = null): Gauge
    {
        return new Gauge($name, $labelValues, []);
    }

    #[\Override]
    public function summary(string $name, array $labelValues, float|int ...$values): Summary
    {
        return new Summary($name, $labelValues, []);
    }

    #[\Override]
    public function histogram(string $name, array $labelValues, float|int ...$values): Histogram
    {
        return new Histogram($name, $labelValues, []);
    }

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
