<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Timer\Handler;

use MakinaCorpus\Profiling\Timer;
use MakinaCorpus\Profiling\Timer\TimerTrace;

/**
 * Handles user trigger.
 */
class TriggerHandlerDecorator implements TraceHandler
{
    private TraceHandler $decorated;
    private string $triggerName; // @phpstan-ignore-line

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

    #[\Override]
    public function setThreshold(?int $memoryThreshold, ?float $timeThreshold): void
    {
        $this->decorated->setThreshold($memoryThreshold, $timeThreshold);
    }

    #[\Override]
    public function onStart(Timer $timer): void
    {
        if ($this->isEnabled()) {
            $this->decorated->onStart($timer);
        }
    }

    #[\Override]
    public function onStop(TimerTrace $trace): void
    {
        if ($this->isEnabled()) {
            $this->decorated->onStop($trace);
        }
    }

    #[\Override]
    public function flush(): void
    {
        // Always passthrought flush(), in order to let all handlers
        // terminate properly, in case it was enabled at some point in
        // time previously.
        $this->decorated->flush();
    }
}
