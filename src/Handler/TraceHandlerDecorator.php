<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Handler;

use MakinaCorpus\Profiling\Timer;
use MakinaCorpus\Profiling\TimerTrace;
use MakinaCorpus\Profiling\TraceHandler;

/**
 * Used when configuring a handler being an arbitrary user service: when
 * working during the compiler pass, other bundles might not have registered
 * their services yet, using a decorator makes the identifier resolution
 * being later during compilation.
 */
class TraceHandlerDecorator implements NamedTraceHandler
{
    private string $name = 'unnamed';

    public function __construct(
        private TraceHandler $decorated,
    ) {}

    #[\Override]
    public function setThreshold(?int $memoryThreshold, ?float $timeThreshold): void
    {
        $this->decorated->setThreshold($memoryThreshold, $timeThreshold);
    }

    #[\Override]
    public function onStart(Timer $timer): void
    {
        $this->decorated->onStart($timer);
    }

    #[\Override]
    public function onStop(TimerTrace $trace): void
    {
        $this->decorated->onStop($trace);
    }

    #[\Override]
    public function flush(): void
    {
        $this->decorated->flush();
    }

    #[\Override]
    public function setName(string $name): void
    { 
        if ($this->decorated instanceof NamedTraceHandler) {
            $this->decorated->setName($name);
        }
        $this->name = $name;
    }

    #[\Override]
    public function getName(): string
    {
        if ($this->decorated instanceof NamedTraceHandler) {
            return $this->decorated->getName();
        }
        return $this->name;
    }
}
