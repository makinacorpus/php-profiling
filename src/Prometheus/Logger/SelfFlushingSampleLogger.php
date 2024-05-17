<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Logger;

use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Sample\Histogram;
use MakinaCorpus\Profiling\Prometheus\Sample\Summary;
use Psr\Log\LoggerInterface;

/**
 * @todo reverse flushIf before creating the new sample, which may be
 *   altered after being saved ...
 */
class SelfFlushingSampleLogger implements SampleLogger
{
    public function __construct(
        private SampleLogger $decorated,
        private int $maxSize = 100,
        private ?LoggerInterface $logger = null,
    ) {}

    private function flushIfMaxSizeExceeded(): void
    {
        $size = $this->decorated->size();

        if ($size >= $this->maxSize) {
            $this->logger?->notice("Self flushing sample logger size ({max}) exceeded ({size}), flushing.", ['max' => $this->maxSize, 'size' => $size]);

            $this->decorated->flush();
        }
    }

    #[\Override]
    public function counter(string $name, array $labelValues, ?int $value = null): Counter
    {
        $this->flushIfMaxSizeExceeded();

        return $this->decorated->counter($name, $labelValues, $value);
    }

    #[\Override]
    public function gauge(string $name, array $labelValues, null|float|int $value = null): Gauge
    {
        $this->flushIfMaxSizeExceeded();

        return $this->decorated->gauge($name, $labelValues, $value);
    }

    #[\Override]
    public function summary(string $name, array $labelValues, float|int ...$values): Summary
    {
        $this->flushIfMaxSizeExceeded();

        return $this->decorated->summary($name, $labelValues, ...$values);
    }

    #[\Override]
    public function histogram(string $name, array $labelValues, float|int ...$values): Histogram
    {
        $this->flushIfMaxSizeExceeded();

        return $this->decorated->histogram($name, $labelValues, ...$values);
    }

    #[\Override]
    public function flush(): void
    {
        $this->decorated->flush();
    }

    #[\Override]
    public function size(): int
    {
        return $this->decorated->size();
    }
}
