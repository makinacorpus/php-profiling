<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Handler;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerTrace;
use MakinaCorpus\Profiling\TraceHandler;

/**
 * Handles user trigger.
 */
class TriggerHandlerDecorator implements TraceHandler
{
    private TraceHandler $decorated;
    private string $triggerName;

    public function __construct(TraceHandler $decorated, string $triggerName)
    {
        $this->decorated = $decorated;
        $this->triggerName = $triggerName;
    }

    /**
     * Is this enabled by trigger.
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setThreshold(?int $memoryThreshold, ?float $timeThreshold): void
    {
        $this->decorated->setThreshold($memoryThreshold, $timeThreshold);
    }

    /**
     * {@inheritdoc}
     */
    public function onStart(Profiler $profiler): void
    {
        if ($this->isEnabled()) {
            $this->decorated->onStart($profiler);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onStop(ProfilerTrace $trace): void
    {
        if ($this->isEnabled()) {
            $this->decorated->onStop($trace);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): void
    {
        // Always passthrought flush(), in order to let all handlers
        // terminate properly, in case it was enabled at some point in
        // time previously.
        $this->decorated->flush();
    }
}
