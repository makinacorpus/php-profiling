<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Sample;

/**
 * Represent an ended profiling trace.
 */
abstract class Sample
{
    /**
     * Exact time at which this sample was created.
     */
    public \DateTimeImmutable $measuredAt;

    public function __construct(
        /**
         * Sample name.
         *
         * If for example you need to profile numerous SQL queries, you may
         * use the "sql_query" sample name, then discriminate using the labels
         * in order to partition the sample data sets.
         */
        public readonly string $name,

        /**
         * Each sample meta-information is defined with a fixed number of
         * label names. Each sample value must give the exact count of those
         * label values in order to be consistent.
         * This will be used for exposing data to prometheus.
         *
         * @var string[]
         */
        public readonly array $labelValues,

        /**
         * Channels are information for filtering samples.
         * Sample collection can be deactivated on a per-channel basis.
         * Sample logging can be routed using channels as well.
         *
         * @var string[]
         */
        public readonly array $channels,
    ) {
        $this->measuredAt = new \DateTimeImmutable();
    }

    /**
     * Compute a unique identifier for storage.
     */
    public function getUniqueId(): string
    {
        return \sha1($this->name . \implode('', $this->labelValues));
    }

    /**
     * A sample can be a collection as well, returns the total sub-sample count.
     */
    public function getSampleCount(): int
    {
        return 1;
    }

    /**
     * Change the measure date.
     */
    protected function updateMeasuredAt(): void
    {
        $this->measuredAt = new \DateTimeImmutable();
    }
}
