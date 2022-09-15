<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

interface ProfilerContextAware
{
    /**
     * Set the profiler context.
     */
    public function setProfilerContext(ProfilerContext $profilerContext): void;
}
