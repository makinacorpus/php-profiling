<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

interface ProfilerContext extends ProfilerFactory
{
    /**
     * Is there at least one profiler running.
     */
    public function isRunning(): bool;

    /**
     * Get all currently registered profilers.
     *
     * @return Profiler[]
     */
    public function getAllProfilers(): iterable;

    /**
     * Stop all profilers, remove all, then return them.
     *
     * It frees all consumed memory so far, it can be called only once.
     *
     * @return Profiler[]
     */
    public function flush(): iterable;
}
