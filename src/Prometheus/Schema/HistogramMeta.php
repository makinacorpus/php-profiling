<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Schema;

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
}
