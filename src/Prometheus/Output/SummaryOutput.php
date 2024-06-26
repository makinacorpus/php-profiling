<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Output;

use MakinaCorpus\Profiling\Prometheus\Sample\Sample;

/**
 * This data structure is used for output/rendering only.
 */
class SummaryOutput extends Sample
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
         * Computed value for the quantile.
         */
        public readonly int|float $value,

        /**
         * Quantile the value is associated to.
         */
        public readonly int|float $quantile,
    ) {
        parent::__construct($name, $labelValues, $channels);
    }
}
