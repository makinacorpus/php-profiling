<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Handler;

use MakinaCorpus\Profiling\ProfilerTrace;

abstract class AbstractHandler implements NamedTraceHandler
{
    private string $name = 'unnamed';
    private bool $hasThreshold = false;
    private ?int $memoryThreshold = null;
    private ?float $timeThreshold = null;

    /**
     * {@inheritdoc}
     */
    public function setThreshold(?int $memoryThreshold, ?float $timeThreshold): void
    {
        $this->hasThreshold = true;
        $this->memoryThreshold = $memoryThreshold;
        $this->timeThreshold = $timeThreshold;
    }

    /**
     * Should the given trace be logged or dumped.
     */
    protected function shouldLog(ProfilerTrace $trace): bool
    {
        return
            (!$this->hasThreshold) ||
            (null !== $this->timeThreshold && $trace->getElapsedTime() >= $this->timeThreshold) ||
            (null !== $this->memoryThreshold && $trace->getMemoryUsage() >= $this->memoryThreshold)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }
}
