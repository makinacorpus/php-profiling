<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Logger;

use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Sample\Histogram;
use MakinaCorpus\Profiling\Prometheus\Sample\Sample;
use MakinaCorpus\Profiling\Prometheus\Sample\Summary;
use MakinaCorpus\Profiling\Prometheus\Schema\Schema;
use MakinaCorpus\Profiling\Prometheus\Storage\Storage;
use Psr\Log\LoggerInterface;

// @todo IDE bug, sorry.
\class_exists(Sample::class);

/**
 * When flushing data, every sample will be stored with its creation timestamp.
 */
class MemorySampleLogger implements SampleLogger
{
    private int $size = 0;
    /** @var Counter[] */
    private array $counters = [];
    /** @var Gauge[] */
    private array $gauges = [];
    /** @var Summary[] */
    private array $summaries = [];
    /** @var Histogram[] */
    private array $histograms = [];

    public function __construct(
        private Schema $schema,
        private Storage $storage,
        private ?LoggerInterface $logger = null,
    ) {}

    #[\Override]
    public function counter(string $name, array $labelValues, ?int $value = null): Counter
    {
        $meta = $this->schema->getCounter($name);

        if (!$meta->isActive()) {
            return new Counter($name, [], []);
        }

        $labelValues = $meta->validateLabelValues($labelValues);

        if (null === $labelValues) {
            $this->logger?->error("{name} counter label value count mismatch the schema definition.", ['name' => $name]);

            return new Counter($name, [], []);
        }

        $key = $meta->computeUniqueStorageKey($labelValues);
        $sample = $this->counters[$key] ?? ($this->counters[$key] = new Counter($name, $labelValues, []));
        $this->size++;

        if ($value) {
            $sample->increment($value);
        } else {
            $sample->increment(1);
        }

        return $sample;
    }

    #[\Override]
    public function gauge(string $name, array $labelValues, null|float|int $value = null): Gauge
    {
        $meta = $this->schema->getGauge($name);

        if (!$meta->isActive()) {
            return new Gauge($name, [], []);
        }

        $labelValues = $meta->validateLabelValues($labelValues);
        if (null === $labelValues) {
            $this->logger?->error("{name} gauge label value count mismatch the schema definition.", ['name' => $name]);

            return new Gauge($name, [], []);
        }

        $key = $meta->computeUniqueStorageKey($labelValues);
        $sample = $this->gauges[$key] ?? ($this->gauges[$key] = new Gauge($name, $labelValues, []));
        $this->size++;

        if (null !== $value) {
            $sample->set($value);
        }

        return $sample;
    }

    #[\Override]
    public function summary(string $name, array $labelValues, float|int ...$values): Summary
    {
        $meta = $this->schema->getSummary($name);

        if (!$meta->isActive()) {
            return new Summary($name, [], []);
        }

        $labelValues = $meta->validateLabelValues($labelValues);
        if (null === $labelValues) {
            $this->logger?->error("{name} summary label value count mismatch the schema definition.", ['name' => $name]);

            return new Summary($name, [], []);
        }

        $key = $meta->computeUniqueStorageKey($labelValues);
        $sample = $this->summaries[$key] ?? ($this->summaries[$key] = new Summary($name, $labelValues, []));
        $this->size++;

        foreach ($values as $value) {
            $sample->add($value);
        }

        return $sample;
    }

    #[\Override]
    public function histogram(string $name, array $labelValues, float|int ...$values): Histogram
    {
        $meta = $this->schema->getHistogram($name);

        if (!$meta->isActive()) {
            return new Histogram($name, [], []);
        }

        $labelValues = $meta->validateLabelValues($labelValues);
        if (null === $labelValues) {
            $this->logger?->error("{name} histogram label value count mismatch the schema definition.", ['name' => $name]);

            return new Histogram($name, [], []);
        }

        $key = $meta->computeUniqueStorageKey($labelValues);
        $sample = $this->histograms[$key] ?? ($this->histograms[$key] = new Histogram($name, $labelValues, []));
        $this->size++;

        foreach ($values as $value) {
            $sample->add($value);
        }

        return $sample;
    }

    #[\Override]
    public function flush(): void
    {
        $this->storage->store(
            $this->schema,
            (function () {
                yield from $this->counters;
                yield from $this->gauges;
                yield from $this->summaries;
                yield from $this->histograms;
            })()
        );

        $this->counters = $this->gauges = $this->summaries = $this->histograms = [];
        $this->size = 0;
    }

    #[\Override]
    public function size(): int
    {
        return $this->size;
    }
}
