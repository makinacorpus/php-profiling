<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

/**
 * Used for profiler trace output.
 *
 * All profiler traces that will go throught will have ended.
 */
interface TraceHandler
{
    /**
     * Set threshold from which traces will be logged.
     */
    public function setThreshold(?int $memoryThreshold, ?float $timeThreshold): void;

    /**
     * Handler profiler start.
     */
    public function onStart(Profiler $profiler): void;

    /**
     * Handle profiler stop.
     */
    public function onStop(ProfilerTrace $trace): void;

    /**
     * Flush any remaining buffer.
     */
    public function flush(): void;
}
