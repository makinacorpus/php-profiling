<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Schema;

/**
 * Counters must be known, they will not be created automatically.
 *
 * This is rigid and makes the bundle harder to setup, but it yield many
 * benefits:
 *
 *  - known measures points can be yield in memory and won't require any
 *    data storage backend round trips while measuring,
 *
 *  - this enforce validation and that label, quantiles and buckets won't
 *    accidentally change over time for a single sample,
 *
 *  - this will also enforce name validation and prevent accidental measures
 *    from being taken.
 */
abstract class Schema
{
    protected array $counters = [];
    protected array $gauges = [];
    protected array $summaries = [];
    protected array $histograms = [];

    public function __construct(
        protected string $namespace,
        protected bool $debug = false,
    ) {}

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    protected function getName(string $name, bool $stripNamespace = false): string
    {
        if ($stripNamespace && \str_starts_with($name, $this->namespace . '_')) {
            return \substr($name, \strlen($this->namespace) + 1);
        }
        return $name;
    }

    protected function registerCounter(CounterMeta $data): void
    {
        if ($this->debug) {
            $data->setDebug(true);
        }
        $this->counters[$data->getName()] = $data;
    }

    public function hasCounter(string $name, bool $stripNamespace = false): bool
    {
        $name = $this->getName($name, $stripNamespace);

        return \array_key_exists($name, $this->counters);
    }

    public function getCounter(string $name, bool $stripNamespace = false): CounterMeta
    {
        $name = $this->getName($name, $stripNamespace);

        return $this->counters[$name] ?? new CounterMeta(name: $name, active: false);
    }

    protected function registerGauge(GaugeMeta $data): void
    {
        if ($this->debug) {
            $data->setDebug(true);
        }
        $this->gauges[$data->getName()] = $data;
    }

    public function hasGauge(string $name, bool $stripNamespace = false): bool
    {
        $name = $this->getName($name, $stripNamespace);

        return \array_key_exists($name, $this->gauges);
    }

    public function getGauge(string $name, bool $stripNamespace = false): GaugeMeta
    {
        $name = $this->getName($name, $stripNamespace);

        return $this->gauges[$name] ?? new GaugeMeta(name: $name, active: false);
    }

    protected function registerSummary(SummaryMeta $data): void
    {
        if ($this->debug) {
            $data->setDebug(true);
        }
        $this->summaries[$data->getName()] = $data;
    }

    public function hasSummary(string $name, bool $stripNamespace = false): bool
    {
        $name = $this->getName($name, $stripNamespace);

        return \array_key_exists($name, $this->summaries);
    }

    public function getSummary(string $name, bool $stripNamespace = false): SummaryMeta
    {
        $name = $this->getName($name, $stripNamespace);

        return $this->summaries[$name] ?? new SummaryMeta(name: $name, active: false);
    }

    protected function registerHistogram(HistogramMeta $data): void
    {
        if ($this->debug) {
            $data->setDebug(true);
        }
        $this->histograms[$data->getName()] = $data;
    }

    public function hasHistogram(string $name, bool $stripNamespace = false): bool
    {
        $name = $this->getName($name, $stripNamespace);

        return \array_key_exists($name, $this->histograms);
    }

    public function getHistogram(string $name, bool $stripNamespace = false): HistogramMeta
    {
        $name = $this->getName($name, $stripNamespace);

        return $this->histograms[$name] ?? new HistogramMeta(name: $name, active: false);
    }
}
