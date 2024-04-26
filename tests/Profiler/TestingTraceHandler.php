<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Profiler;

use MakinaCorpus\Profiling\Handler\AbstractHandler;
use MakinaCorpus\Profiling\Timer;
use MakinaCorpus\Profiling\TimerTrace;

final class TestingTraceHandler extends AbstractHandler
{
    private array $all = [];
    private array $stopped = [];
    private array $allNoFlush = [];

    public function getAll(): array
    {
        return $this->all;
    }

    public function getAllNoFlush(): array
    {
        return $this->allNoFlush;
    }

    public function getStopped(): array
    {
        return $this->stopped;
    }

    #[\Override]
    public function onStart(Timer $timer): void
    {
        $this->all[] = $timer->getAbsoluteName();
        $this->allNoFlush[] = $timer->getAbsoluteName();
    }

    #[\Override]
    public function onStop(TimerTrace $trace): void
    {
        $this->stopped[] = $trace->getAbsoluteName();
    }

    #[\Override]
    public function flush(): void
    {
        $this->all = [];
    }
}
