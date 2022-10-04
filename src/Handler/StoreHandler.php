<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Handler;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerTrace;
use MakinaCorpus\Profiling\TraceStore;

/**
 * Composer require sentry/sentry:^3.8
 */
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

    /**
     * {@inheritdoc}
     */
    public function onStart(Profiler $profiler): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function onStop(ProfilerTrace $trace): void
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

    /**
     * {@inheritdoc}
     */
    public function flush(): void
    {
        $traces = $this->buffer;
        $this->buffer = [];
        $this->store->store(...$traces);
    }
}
