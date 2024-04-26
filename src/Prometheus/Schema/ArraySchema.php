<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Schema;

class ArraySchema extends Schema
{
    public function __construct(string $namespace, array $data, bool $debug = false)
    {
        parent::__construct($namespace, $debug);

        foreach ($data as $name => $def) {
            match ($def['type']) {
                'counter' => $this->registerCounter(
                    new CounterMeta(
                        name: $name,
                        labels: $def['labels'] ?? [],
                        help: $def['help'] ?? null,
                        active: (bool) ($def['active'] ?? true)
                    )
                ),
                'gauge' => $this->registerGauge(
                    new GaugeMeta(
                        name: $name,
                        labels: $def['labels'] ?? [],
                        help: $def['help'] ?? null,
                        active: (bool) ($def['active'] ?? true)
                    )
                ),
                'summary' => $this->registerSummary(
                    new SummaryMeta(
                        name: $name,
                        labels: $def['labels'] ?? [],
                        help: $def['help'] ?? null,
                        active: (bool) ($def['active'] ?? true),
                        quantiles: $def['quantiles'] ?? null,
                        maxAge: $def['max_age'] ?? null,
                    )
                ),
                'histogram' => $this->registerHistogram(
                    new HistogramMeta(
                        name: $name,
                        labels: $def['labels'] ?? [],
                        help: $def['help'] ?? null,
                        active: (bool) ($def['active'] ?? true),
                        buckets: $def['buckets'] ?? null,
                    )
                ),
                default => null,
            };
        }
    }
}
