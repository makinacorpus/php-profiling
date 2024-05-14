<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Sample\Histogram;
use MakinaCorpus\Profiling\Prometheus\Sample\Summary;

interface Profiler
{
    /**
     * Create a timer in this profiler, do not start it..
     *
     * In case the current profiler or timer was closed or flushed, this
     * will return a null instance.
     */
    public function createTimer(?string $name = null, ?array $channels = null): Timer;

    /**
     * Start new timer in this profiler, start it.
     *
     * In case the current profiler or timer was closed or flushed, this
     * will return a null instance.
     */
    public function timer(?string $name = null, ?array $channels = null): Timer;

    /**
     * Enable or disable profiling.
     *
     * It won't shut down currently running timers, it will just prevent
     * new timers creation.
     */
    public function toggle(bool $enabled): void;

    /**
     * Global killswitch. Tell if profiling is enabled at all.
     *
     * If disabled, all timers will be null instances, no storage will be done.
     */
    public function isEnabled(): bool;

    /**
     * Prometheus killswitch. Tell if sample metrics are enabled.
     *
     * If disabled, samples will be null instances, no storage will be done.
     */
    public function isPrometheusEnabled(): bool;

    /**
     * Enter new execution context.
     */
    public function enterContext(RequestContext $context, bool $enablePrometheus = false): void;

    /**
     * Get current execution context.
     */
    public function getContext(): RequestContext;

    /**
     * Exit current execution context and flush.
     */
    public function exitContext(): void;

    /**
     * Increment a prometheus counter sample.
     */
    public function counter(string $name, array $labelValues, ?int $value = null): Counter;

    /**
     * Set a prometheus gauge sample value.
     */
    public function gauge(string $name, array $labelValues, ?float $value = null): Gauge;

    /**
     * Set one or more prometheus histogram sample values.
     */
    public function histogram(string $name, array $labelValues, int|float ...$values): Histogram;

    /**
     * Set one or more prometheus summary sample values.
     */
    public function summary(string $name, array $labelValues, int|float ...$values): Summary;

    /**
     * Manually flush (stop all timers, send sample data to storage).
     */
    public function flush(): void;
}
