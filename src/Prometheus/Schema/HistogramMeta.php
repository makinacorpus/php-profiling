<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Schema;

use MakinaCorpus\Profiling\Prometheus\Error\SchemaError;

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
