<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Output;

use MakinaCorpus\Profiling\Prometheus\Sample\Sample;

/**
 * This data structure is used for output/rendering only.
 */
class HistogramOutput extends Sample
{
    public function __construct(
        /**
         * Sample name.
         */
        string $name,

        /**
         * Label values.
         *
         * @var string[]
         */
        array $labelValues,

        /**
         * Channels.
         *
         * @var string[]
         */
        array $channels,

        /**
         * Count for the bucket.
         */
        public readonly int $count,

        /**
         * Quantile the value is associated to.
         */
        public readonly int|float|string $bucket,
    ) {
        parent::__construct($name, $labelValues, $channels);
    }
}
