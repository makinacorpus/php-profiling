<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Sample;

class Histogram extends Sample
{
    /** @var HistogramItem[] */
    private array $samples = [];

    public function add(float $value): void
    {
        $this->samples[] = new HistogramItem($value);
    }

    /**
     * @return HistogramItem[]
     */
    public function getValues(): array
    {
        return $this->samples;
    }

    #[\Override]
    public function getSampleCount(): int
    {
        return \count($this->samples);
    }
}
