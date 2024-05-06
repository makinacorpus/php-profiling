<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Timer\Handler;

use MakinaCorpus\Profiling\Timer;
use MakinaCorpus\Profiling\Timer\Storage\TraceStore;
use MakinaCorpus\Profiling\Timer\TimerTrace;

class StoreHandler extends AbstractHandler
{
    private TraceStore $store;
    private bool $direct = true;
    private array $buffer = [];

    public function __construct(TraceStore $store, bool $direct = true)
    {
        $this->store = $store;
        $this->direct = $direct;
    }

    #[\Override]
    public function onStart(Timer $timer): void
    {
    }

    #[\Override]
    public function onStop(TimerTrace $trace): void
    {
        if (!$this->shouldLog($trace)) {
            return;
        }

        if ($this->direct) {
            $this->store->store($trace);
        } else {
            $this->buffer[] = $trace;
        }
    }

    #[\Override]
    public function flush(): void
    {
        $traces = $this->buffer;
        $this->buffer = [];
        $this->store->store(...$traces);
    }
}
