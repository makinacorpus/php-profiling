<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Schema;

use MakinaCorpus\Profiling\Prometheus\Error\SchemaError;
use MakinaCorpus\Profiling\Prometheus\Output\HistogramOutput;
use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Sample\Sample;

// @todo IDE bug
\class_exists(Sample::class);

class HistogramMeta extends AbstractMeta
{
    private array $buckets;

    public function __construct(
        string $name,
        array $labels = [],
        ?string $help = null,
        bool $active = true,
        ?array $buckets = null,
    ) {
        parent::__construct($name, $labels, $help, $active);

        if (null === $buckets) {
            $this->buckets = self::getDefaultBuckets();
        } else {
            \sort($buckets);
            $this->buckets = $buckets;
        }
    }

    /**
     * Create list of exponential buckets.
     */
    public static function getExponentialBuckets(float $start, float $growthFactor, int $numberOfBuckets): array
    {
        if ($start <= 0) {
            throw new SchemaError('Starting position of a bucket set must be a positive integer.');
        }
        if ($growthFactor <= 1) {
            throw new SchemaError('Growth factor must greater than 1.');
        }
        if ($numberOfBuckets < 1) {
            throw new SchemaError('Number of buckets in set must be a positive integer.');
        }

        $ret = [];
        for ($i = 0; $i < $numberOfBuckets; $i++) {
            $ret[$i] = $start;
            $start *= $growthFactor;
        }

        return $ret;
    }

    /**
     * List of default buckets.
     */
    public static function getDefaultBuckets(): array
    {
        return [0.005, 0.01, 0.025, 0.05, 0.075, 0.1, 0.25, 0.5, 0.75, 1.0, 2.5, 5.0, 7.5, 10.0];
    }

    /**
     * Buckets, as an array of float values.
     *
     * @return float[]
     */
    public function getBuckets(): array
    {
        return $this->buckets;
    }

    /**
     * Create output samples from given values.
     *
     * It will get the user input values, and redispatch those into the buckets
     * this class contains. Value distribution doesn't matter since only the
     * total sum is returned.
     *
     * @todo This method is unperformant, but it works.
     *
     * @param array $input
     *   Keys are bucket names, values is a two-value array whose values are
     *   the count, and value sum. It may or may not contain a "+Inf" bucket.
     *
     * @return Sample[]
     */
    public function createOutput(string $name, array $labelValues, array $input): array
    {
        $rearranged = [];
        $infCount = 0;
        $sumTotal = 0;

        foreach ($this->buckets as $originalBucket) {
            $rearranged[$originalBucket] = 0;
        }

        foreach ($input as $bucket => $data) {
            list ($count, $sum) = $data;

            $sumTotal += $sum;
            $infCount += $count;

            if ('+Inf' !== $bucket) {
                foreach ($this->buckets as $originalBucket) {
                    if ($bucket <= $originalBucket) {
                        $rearranged[$originalBucket] += $count;
                    }
                }
            }
        }

        $ret = [];
        foreach ($rearranged as $bucket => $count) {
            $ret[] = new HistogramOutput($name, $labelValues, [], $count, $bucket);
        }
        $ret[] = new HistogramOutput($name, $labelValues, [], $infCount, '+Inf');

        $ret[] = (new Counter($name . '_count', $labelValues, []))->increment($infCount);
        $ret[] = (new Gauge($name . '_sum', $labelValues, []))->set($sumTotal);

        return $ret;
    }

    /**
     * Find bucket for the given value.
     */
    public function findBucketFor(int|float $value): float|string
    {
        foreach ($this->buckets as $bucket) {
            if ($value <= $bucket) {
                return $bucket;
            }
        }
        return '+Inf';
    }
}
