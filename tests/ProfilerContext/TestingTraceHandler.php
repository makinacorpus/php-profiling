<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\ProfilerContext;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerTrace;
use MakinaCorpus\Profiling\Handler\AbstractHandler;

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

    /**
     * {@inheritdoc}
     */
    public function onStart(Profiler $profiler): void
    {
        $this->all[] = $profiler->getAbsoluteName();
        $this->allNoFlush[] = $profiler->getAbsoluteName();
    }

    /**
     * {@inheritdoc}
     */
    public function onStop(ProfilerTrace $trace): void
    {
        $this->stopped[] = $trace->getAbsoluteName();
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): void
    {
        $this->all = [];
    }
}
