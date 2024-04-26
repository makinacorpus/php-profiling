<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Output;

use MakinaCorpus\Profiling\Prometheus\Sample\Sample;

// @todo IDE bug, sorry.
\class_exists(Sample::class);

/**
 * Contains all measures from a single name with the same label values.
 *
 * This data structure is used for output/rendering only.
 */
class SampleCollection
{
    const TYPE_COUNTER = 'counter';
    const TYPE_GAUGE = 'gauge';
    const TYPE_HISTOGRAM = 'histogram';
    const TYPE_SUMMARY = 'summary';

    public function __construct(
        /**
         * Sample name.
         */
        public readonly string $name,

        /**
         * Sample help text.
         */
        public readonly string $help,

        /**
         * Sample type, eg. one of this class constants.
         */
        public readonly string $type,

        /**
         * Label names.
         */
        public readonly array $labelNames,

        /**
         * All samples collected for the same name and label values.
         *
         * @var Sample[]
         */
        public readonly iterable $samples,
    ) {}
}
