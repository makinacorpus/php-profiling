<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Logger;

use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Sample\Summary;

/**
 * @todo reverse flushIf before creating the new sample, which may be
 *   altered after being saved ...
 */
class SelfFlushingSampleLogger implements SampleLogger
{
    private int $ticks = 0;

    public function __construct(
        private SampleLogger $decorated,
        private int $maxSize = 100,
    ) {}

    private function flushIfMaxSizeExceeded(): void
    {
        $this->ticks++;
        if ($this->ticks > 5) {
            $this->ticks = 0;
            if ($this->decorated->size() > $this->maxSize) {
                $this->decorated->flush();
            }
        }
    }

    #[\Override]
    public function counter(string $name, array $labelValues, ?int $value = null): Counter
    {
        try {
            return $this->decorated->counter($name, $labelValues, $value);
        } finally {
            $this->flushIfMaxSizeExceeded();
        }
    }

    #[\Override]
    public function gauge(string $name, array $labelValues, null|float|int $value = null): Gauge
    {
        try {
            return $this->decorated->gauge($name, $labelValues, $value);
        } finally {
            $this->flushIfMaxSizeExceeded();
        }
    }

    #[\Override]
    public function summary(string $name, array $labelValues, float|int ...$values): Summary
    {
        try {
            return $this->decorated->summary($name, $labelValues, ...$values);
        } finally {
            $this->flushIfMaxSizeExceeded();
        }
    }

    // #[\Override]
    // public function histogram(string $name, array $labelValues, float ...$values): HistogramSample;

    #[\Override]
    public function flush(): void
    {
        $this->decorated->flush();
        $this->ticks = 0;
    }

    #[\Override]
    public function size(): int
    {
        return $this->decorated->size();
    }
}
