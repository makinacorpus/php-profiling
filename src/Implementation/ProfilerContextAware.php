<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Implementation;

use MakinaCorpus\Profiling\ProfilerContext;

interface ProfilerContextAware
{
    /**
     * Set the profiler context.
     */
    public function setProfilerContext(ProfilerContext $context): void;
}
