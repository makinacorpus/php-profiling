<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Profiler;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\Prometheus\Logger\NullSampleLogger;
use MakinaCorpus\Profiling\Prometheus\Logger\SampleLogger;
use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Sample\Histogram;
use MakinaCorpus\Profiling\Prometheus\Sample\Summary;
use MakinaCorpus\Profiling\RequestContext;
use MakinaCorpus\Profiling\Timer;
use Psr\Log\LoggerInterface;

/**
 * Default implementation, stores information in memory and push it to
 * stores on flush calls.
 */
final class DefaultProfiler implements Profiler
{
    private ?RequestContext $context = null;
    private NullSampleLogger $nullSampleLogger;
    private bool $prometheusReallyEnabled = false;
    /** @var Timer[] */
    private array $timers = [];

    public function __construct(
        private bool $enabled = false,
        private bool $prometheusEnabled = false,
        private ?SampleLogger $sampleLogger = null,
        private ?LoggerInterface $logger = null,
    ) {
        $this->nullSampleLogger = new NullSampleLogger();
    }

    #[\Override]
    public function toggle(bool $enabled, bool $prometheusEnabled = false): void
    {
        if ($enabled) {
            if ($prometheusEnabled) {
                $this->logger?->debug("Profiler enabled with prometheus.");
            } else {
                $this->logger?->debug("Profiler enabled without prometheus.");
            }
        } else {
            $this->logger?->debug("Profiler disabled.");
        }

        $this->enabled = $enabled;
    }

    #[\Override]
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    #[\Override]
    public function isPrometheusEnabled(): bool
    {
        return $this->prometheusReallyEnabled;
    }

    #[\Override]
    public function enterContext(RequestContext $context, bool $enablePrometheus = false): void
    {
        $this->logger?->debug("Entering profiler context {name}.", ['name' => $context->toString()]);

        if ($this->context) {
            $this->logger?->info("Profiler context was already set, flushing and recreating.");
            $this->flush();
        }

        $this->prometheusReallyEnabled = $enablePrometheus && $this->prometheusEnabled;
        $this->context = $context;
    }

    #[\Override]
    public function getContext(): RequestContext
    {
        if (null === $this->context) {
            $this->logger?->error("Profiler context was empty, using null context.");

            return RequestContext::null();
        }

        return $this->context;
    }

    #[\Override]
    public function exitContext(): void
    {
        if ($this->context) {
            $this->logger?->debug("Exiting profiler context {name}.", ['name' => $this->context->toString()]);
        } else {
            $this->logger?->error("Exiting profiler context but no context was set.");
        }

        try {
            $this->flush();
        } finally {
            $this->context = null;
            $this->prometheusReallyEnabled = false;
        }
    }

    #[\Override]
    public function createTimer(?string $name = null, ?array $channels = null): Timer
    {
        $timer = new Timer($name, null, $channels);

        if ($this->enabled) {
            $this->timers[] = $timer;
        }

        return $timer;
    }

    #[\Override]
    public function timer(?string $name = null, ?array $channels = null): Timer
    {
        return $this->createTimer($name, $channels)->execute();
    }

    #[\Override]
    public function flush(): void
    {
        // Flush the sample logger first, since we are timing it in the event
        // listener, so that timers take into account its storage time.
        try {
            $this->sampleLogger?->flush();
        } catch (\Throwable) {
            // Let it pass and stop timers.
        }

        // Stop all timers.
        try {
            foreach ($this->timers as $timer) {
                $timer->stop();
            }
        } finally {
            $this->timers = [];
        }
    }

    #[\Override]
    public function counter(string $name, array $labelValues, ?int $value = null): Counter
    {
        return $this->getSampleLogger()->counter($name, $labelValues, $value);
    }

    #[\Override]
    public function gauge(string $name, array $labelValues, ?float $value = null): Gauge
    {
        return $this->getSampleLogger()->gauge($name, $labelValues, $value);
    }

    #[\Override]
    public function histogram(string $name, array $labelValues, int|float ...$values): Histogram
    {
        return $this->getSampleLogger()->histogram($name, $labelValues, ...$values);
    }

    #[\Override]
    public function summary(string $name, array $labelValues, int|float ...$values): Summary
    {
        return $this->getSampleLogger()->summary($name, $labelValues, ...$values);
    }

    /**
     * Get currently active sample logger.
     */
    protected function getSampleLogger(): SampleLogger
    {
        return (!$this->enabled || !$this->prometheusEnabled || !$this->sampleLogger) ? $this->nullSampleLogger : $this->sampleLogger;
    }
}
