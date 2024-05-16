<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Schema;

use MakinaCorpus\Profiling\Prometheus\Output\SummaryOutput;
use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Sample\Sample;

// @todo IDE bug
\class_exists(Sample::class);

class SummaryMeta extends AbstractMeta
{
    private array $quantiles;
    private int $maxAge;

    public function __construct(
        string $name,
        array $labels = [],
        ?string $help = null,
        bool $active = true,
        ?array $quantiles = null,
        ?int $maxAge = null,
    ) {
        parent::__construct($name, $labels, $help, $active);

        if (null === $quantiles) {
            $this->quantiles = self::getDefaultQuantiles();
        } else {
            \sort($quantiles);
            $this->quantiles = $quantiles;
        }

        $this->maxAge = $maxAge ?? 600;
    }

    /**
     * List of default quantiles.
     */
    public static function getDefaultQuantiles(): array
    {
        return [0.01, 0.05, 0.5, 0.95, 0.99];
    }

    /**
     * Taken from https://www.php.net/manual/fr/function.stats-stat-percentile.php#79752
     *
     * @param float[] $values
     * @param float $quantile
     *
     * @return float
     */
    public static function computeQuantiles(array $values, float $quantile, bool $sorted = false): float
    {
        if (!$sorted) {
            \sort($values);
        }

        $count = \count($values);
        if ($count === 0) {
            return 0;
        }

        $j = \floor($count * $quantile);
        $r = $count * $quantile - $j;
        if (0.0 === $r) {
            return (float) $values[$j - 1];
        }
        return (float) $values[$j];
    }

    /**
     * Quantiles, as an array of float values.
     *
     * @return float[]
     */
    public function getQuantiles(): array
    {
        return $this->quantiles;
    }

    /**
     * Samples max age in seconds.
     */
    public function getMaxAge(): int
    {
        return $this->maxAge;
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
     * @param float[] $input
     *   All values of the (sample name, labels) couple, ordered.
     *
     * @return Sample[]
     */
    public function createOutput(string $name, array $labelValues, array $input): array
    {
        $ret = [];
        \sort($input);

        foreach ($this->quantiles as $quantile) {
            // Compute quantiles and set a summary sample in list for
            // each computed quantile.
            $ret[] =(new SummaryOutput($name, $labelValues, [], self::computeQuantiles($input, $quantile, true), $quantile));
        }

        $ret[] = (new Counter($name . '_count', $labelValues, []))->increment(\count($input));
        $ret[] = (new Gauge($name . '_sum', $labelValues, []))->set(\array_sum($input));

        return $ret;
    }
}
