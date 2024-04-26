<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Profiler;

use MakinaCorpus\Profiling\ContextInfo;
use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\Timer;
use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Sample\Summary;
use MakinaCorpus\Profiling\Timer\NullTimer;

/**
 * @codeCoverageIgnore
 */
final class NullProfiler implements Profiler
{
    #[\Override]
    public function createTimer(?string $name = null, ?array $channels = null): Timer
    {
        return new NullTimer();
    }

    #[\Override]
    public function timer(?string $name = null, ?array $channels = null): Timer
    {
        return new NullTimer();
    }

    #[\Override]
    public function toggle(bool $enabled): void
    {
    }

    #[\Override]
    public function isEnabled(): bool
    {
        return false;
    }

    #[\Override]
    public function isPrometheusEnabled(): bool
    {
        return false;
    }

    #[\Override]
    public function enterContext(ContextInfo $context, bool $enablePrometheus = false): void
    {
    }

    #[\Override]
    public function getContext(): ContextInfo
    {
        return ContextInfo::null();
    }

    #[\Override]
    public function exitContext(): void
    {
    }

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

    #[\Override]
    public function flush(): void
    {
    }
}
