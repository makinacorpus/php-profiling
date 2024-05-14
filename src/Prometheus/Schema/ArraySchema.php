<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Schema;

use MakinaCorpus\Profiling\Prometheus\Error\SchemaError;

class ArraySchema extends Schema
{
    public function __construct(string $namespace, array $data, bool $debug = false)
    {
        parent::__construct($namespace, $debug);

        foreach ($data as $name => $def) {
            match ($def['type'] ?? null) {
                'counter' => $this->registerCounter(
                    new CounterMeta(
                        active: (bool) ($def['active'] ?? true),
                        help: $def['help'] ?? null,
                        labels: $this->validateLabels($name, $def['labels'] ?? []),
                        name: $name,
                    )
                ),
                'gauge' => $this->registerGauge(
                    new GaugeMeta(
                        active: (bool) ($def['active'] ?? true),
                        help: $def['help'] ?? null,
                        labels: $this->validateLabels($name, $def['labels'] ?? []),
                        name: $name,
                    )
                ),
                'summary' => $this->registerSummary(
                    new SummaryMeta(
                        active: (bool) ($def['active'] ?? true),
                        help: $def['help'] ?? null,
                        labels: $this->validateLabels($name, $def['labels'] ?? []),
                        maxAge: $def['max_age'] ?? null,
                        name: $name,
                        quantiles: $this->validateQuantiles($name, $def['quantiles'] ?? null),
                    )
                ),
                'histogram' => $this->registerHistogram(
                    new HistogramMeta(
                        active: (bool) ($def['active'] ?? true),
                        buckets: $this->validateBuckets($name, $def['buckets'] ?? null),
                        help: $def['help'] ?? null,
                        labels: $this->validateLabels($name, $def['labels'] ?? []),
                        name: $name,
                    )
                ),
                default => throw new SchemaError(\sprintf("'%s': invalid schema, type '%s' is unknown", $name, $def['type'] ?? '<null>')),
            };
        }
    }

    private function validateLabels(string $name, mixed $labels): array
    {
        if (!\is_array($labels)) {
            throw new SchemaError(\sprintf("'%s' invalid schema, 'labels' must be an array.", $name));
        }
        foreach ($labels as $index => $label) {
            if (!\is_string($label)) {
                throw new SchemaError(\sprintf("'%s' invalid schema, 'labels' item #%s must be a string value.", $name, $index));
            }
        }
        return $labels;
    }

    private function validateBuckets(string $name, mixed $buckets): ?array
    {
        if (null === $buckets) {
            return null;
        }
        if (!\is_array($buckets)) {
            throw new SchemaError(\sprintf("'%s' invalid schema, 'buckets' must be an array.", $name));
        }
        foreach ($buckets as $index => $bucket) {
            if (!\is_numeric($bucket)) {
                throw new SchemaError(\sprintf("'%s' invalid schema, 'buckets' item #%s must be a int or a float value.", $name, $index));
            }
        }
        return $buckets;
    }

    private function validateQuantiles(string $name, mixed $quantiles): ?array
    {
        if (null === $quantiles) {
            return null;
        }
        if (!\is_array($quantiles)) {
            throw new SchemaError(\sprintf("'%s' invalid schema, 'quantiles' must be an array.", $name));
        }
        foreach ($quantiles as $index => $quantile) {
            if (!\is_numeric($quantile)) {
                throw new SchemaError(\sprintf("'%s' invalid schema, 'quantiles' item #%s must be a int or a float value.", $name, $index));
            }
        }
        return $quantiles;
    }
}
