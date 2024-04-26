<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Sample;

class Summary extends Sample
{
    /** @var SummaryItem[] */
    private array $samples = [];

    public function add(float $value): void
    {
        $this->samples[] = new SummaryItem($value);
    }

    /**
     * @return SummaryItem[]
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
