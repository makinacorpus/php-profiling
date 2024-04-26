<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Handler;

use MakinaCorpus\Profiling\TimerTrace;

abstract class AbstractHandler implements NamedTraceHandler
{
    private string $name = 'unnamed';
    private bool $hasThreshold = false;
    private ?int $memoryThreshold = null;
    private ?float $timeThreshold = null;

    #[\Override]
    public function setThreshold(?int $memoryThreshold, ?float $timeThreshold): void
    {
        $this->hasThreshold = true;
        $this->memoryThreshold = $memoryThreshold;
        $this->timeThreshold = $timeThreshold;
    }

    /**
     * Should the given trace be logged or dumped.
     */
    protected function shouldLog(TimerTrace $trace): bool
    {
        return
            (!$this->hasThreshold) ||
            (null !== $this->timeThreshold && $trace->getElapsedTime() >= $this->timeThreshold) ||
            (null !== $this->memoryThreshold && $trace->getMemoryUsage() >= $this->memoryThreshold)
        ;
    }

    #[\Override]
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }
}
