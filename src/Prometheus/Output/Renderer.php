<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Output;

use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Schema\Schema;
use MakinaCorpus\Profiling\Prometheus\Storage\Storage;

final class Renderer
{
    /**
     * Render metrics in stdout.
     */
    public function echo(Storage $storage, Schema $schema): void
    {
        foreach ($storage->collect($schema) as $family) {
            \assert($family instanceof SampleCollection);

            echo "# HELP ", $family->name, " ", $family->help, "\n";
            echo "# TYPE ", $family->name, " ", $family->type, "\n";

            foreach ($family->samples as $sample) {
                if ($sample instanceof Counter) {
                    echo $sample->name, $this->renderLabels($family->labelNames, $sample->labelValues), ' ', $sample->getValue(), "\n";
                } else if ($sample instanceof Gauge) {
                    echo $sample->name, $this->renderLabels($family->labelNames, $sample->labelValues), ' ', $sample->getValue(), "\n";
                } else if ($sample instanceof HistogramOutput) {
                    echo $sample->name, $this->renderLabels($family->labelNames, $sample->labelValues, ['le' => $sample->bucket]), ' ', $sample->count, "\n";
                } else if ($sample instanceof SummaryOutput) {
                    echo $sample->name, $this->renderLabels($family->labelNames, $sample->labelValues, ['quantile' => $sample->quantile]), ' ', $sample->value, "\n";
                }
            }
        }
    }

    /**
     * Render metrics in text.
     */
    public function render(Storage $storage, Schema $schema): string
    {
        $ret = '';
        \ob_start();
        try {
            $this->echo($storage, $schema);
        } finally {
            $ret = \ob_get_clean();
        }
        return $ret;
    }

    /**
     * Render labels.
     */
    private function renderLabels(array $names, array $values, array $additional = []): string
    {
        if (!$names) {
            return '';
        }
        $ret = '{';
        $values = \array_values($values);
        foreach (\array_values($names) as $index => $name) {
            $ret .= $name . '="' . $this->escapeLabelValue($values[$index] ?? '') . '",';
        }
        if ($additional) {
            foreach ($additional as $name => $value) {
                $ret .= $name . '="' . $this->escapeLabelValue($value) . '",';
            }
        }
        return \substr($ret, 0, -1) . '}';
    }

    /**
     * Escape label value string.
     */
    private function escapeLabelValue(mixed $value): string
    {
        return \str_replace(["\\", "\n", "\""], ["\\\\", "\\n", "\\\""], (string) $value);
    }
}
