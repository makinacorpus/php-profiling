<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Legacy\Bridge\Profiling\Handler;

use MakinaCorpus\Profiling\Handler\AbstractHandler;
use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\Timer;
use MakinaCorpus\Profiling\TimerTrace;

class MetricsHandler extends AbstractHandler
{
    public function __construct(
        private Profiler $profiler,
    ) {}

    #[\Override]
    public function onStart(Timer $timer): void
    {
    }

    #[\Override]
    public function onStop(TimerTrace $trace): void
    {
        $context = $this->profiler->getContext();
        $name = $this->getName();
        $labels = [$context->route, $context->method];
        $this->profiler->counter($name . '_total', $labels, 1);
        $this->profiler->summary($name . '_duration_msec', $labels, $trace->getElapsedTime());
    }

    #[\Override]
    public function flush(): void
    {
    }
}
