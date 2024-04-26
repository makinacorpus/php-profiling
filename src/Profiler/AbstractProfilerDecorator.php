<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Profiler;

use MakinaCorpus\Profiling\ContextInfo;
use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Sample\Summary;
use MakinaCorpus\Profiling\Timer;

abstract class AbstractProfilerDecorator implements Profiler
{
    protected Profiler $decorated;

    public function __construct(Profiler $decorated)
    {
        $this->decorated = $decorated;
    }

    #[\Override]
    public function createTimer(?string $name = null, ?array $channels = null): Timer
    {
        return $this->decorated->createTimer($name, $channels);
    }

    #[\Override]
    public function timer(?string $name = null, ?array $channels = null): Timer
    {
        return $this->createTimer($name, $channels)->execute();
    }

    #[\Override]
    public function toggle(bool $enabled): void
    {
        $this->decorated->toggle($enabled);
    }

    #[\Override]
    public function isEnabled(): bool
    {
        return $this->decorated->isEnabled();
    }

    #[\Override]
    public function isPrometheusEnabled(): bool
    {
        return $this->decorated->isPrometheusEnabled();
    }

    #[\Override]
    public function enterContext(ContextInfo $context, bool $enablePrometheus = false): void
    {
        $this->decorated->enterContext($context, $enablePrometheus);
    }

    #[\Override]
    public function getContext(): ContextInfo
    {
        return $this->decorated->getContext();
    }

    #[\Override]
    public function exitContext(): void
    {
        $this->decorated->exitContext();
    }

    #[\Override]
    public function counter(string $name, array $labelValues, ?int $value = null): Counter
    {
        return $this->decorated->counter($name, $labelValues, $value);
    }

    #[\Override]
    public function gauge(string $name, array $labelValues, ?float $value = null): Gauge
    {
        return $this->decorated->gauge($name, $labelValues, $value);
    }

    #[\Override]
    public function summary(string $name, array $labelValues, float ...$values): Summary
    {
        return $this->decorated->summary($name, $labelValues, ...$values);
    }

    #[\Override]
    public function flush(): void
    {
        $this->decorated->flush();
    }
}
