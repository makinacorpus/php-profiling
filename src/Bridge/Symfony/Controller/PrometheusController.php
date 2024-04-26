<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony\Controller;

use MakinaCorpus\Profiling\Prometheus\Output\SampleCollection;
use MakinaCorpus\Profiling\Prometheus\Output\SummaryOutput;
use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Schema\Schema;
use MakinaCorpus\Profiling\Prometheus\Storage\Storage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class PrometheusController
{
    public function __construct(
        private ?string $accessToken,
        private Storage $storage,
        private Schema $schema,
    ) {}

    /**
     * Fetch metrics endpoint.
     */
    public function metrics(Request $request): Response
    {
        // @todo Implement bearer token.
        if (!$this->accessToken || $request->get('access_token') !== $this->accessToken) {
            // Security components might not installed/enabled.
            throw \class_exists(AccessDeniedException::class) ? new AccessDeniedException() : new \Exception();
        }

        return new StreamedResponse(
            callback: function () {
                foreach ($this->storage->collect($this->schema) as $family) {
                    \assert($family instanceof SampleCollection);

                    echo "# HELP ", $family->name, " ", $family->help, "\n";
                    echo "# TYPE ", $family->name, " ", $family->type, "\n";

                    foreach ($family->samples as $sample) {
                        if ($sample instanceof Counter) {
                            echo $sample->name, $this->renderLabels($family->labelNames, $sample->labelValues), ' ', $sample->getValue(), "\n";
                        } else if ($sample instanceof Gauge) {
                            echo $sample->name, $this->renderLabels($family->labelNames, $sample->labelValues), ' ', $sample->getValue(), "\n";
                        } else if ($sample instanceof SummaryOutput) {
                            echo $sample->name, $this->renderLabels($family->labelNames, $sample->labelValues, ['quantile' => $sample->quantile]), ' ', $sample->value, "\n";
                        }
                    }
                }
            },
        );
    }

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

    private function escapeLabelValue(mixed $value): string
    {
        return \str_replace(["\\", "\n", "\""], ["\\\\", "\\n", "\\\""], (string) $value);
    }
}
