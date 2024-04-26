<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

interface ProfilerAware
{
    /**
     * Set the profiler.
     */
    public function setProfiler(Profiler $profiler): void;
}
