<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Schema;

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
     * taken from https://www.php.net/manual/fr/function.stats-stat-percentile.php#79752
     * @param float[] $arr must be sorted
     * @param float $q
     *
     * @return float
     */
    public static function computeQuantiles(array $arr, float $q): float
    {
        $count = \count($arr);
        if ($count === 0) {
            return 0;
        }

        $j = \floor($count * $q);
        $r = $count * $q - $j;
        if (0.0 === $r) {
            return $arr[$j - 1];
        }
        return $arr[$j];
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
}
