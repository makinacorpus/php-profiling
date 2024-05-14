<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Logger;

use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Sample\Histogram;
use MakinaCorpus\Profiling\Prometheus\Sample\Summary;

/**
 * Sample logger acts as both a sample factory for creating new sample
 * instances and a journal in which they will be stored.
 *
 * A sample logger can be flushed at any moment (which means that samples
 * are sent to storage).
 */
interface SampleLogger
{
    /**
     * Increment a counter.
     */
    public function counter(string $name, array $labelValues, ?int $value = null): Counter;

    /**
     * Set a gauge value.
     */
    public function gauge(string $name, array $labelValues, null|float|int $value = null): Gauge;

    /**
     * Set one or more summary values.
     */
    public function summary(string $name, array $labelValues, float|int ...$values): Summary;

    /**
     * Set one or more histogram values.
     */
    public function histogram(string $name, array $labelValues, float|int ...$values): Histogram;

    /**
     * Send everything to storage and delete from memory.
     */
    public function flush(): void;

    /**
     * Get current log size (number of samples inside).
     */
    public function size(): int;
}
