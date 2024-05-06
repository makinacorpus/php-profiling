<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Legacy;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\Timer;
use MakinaCorpus\Profiling\Timer\Handler\AbstractHandler;
use MakinaCorpus\Profiling\Timer\TimerTrace;

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
