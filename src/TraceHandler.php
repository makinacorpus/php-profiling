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
