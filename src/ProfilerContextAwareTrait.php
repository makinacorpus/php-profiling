<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

use MakinaCorpus\Profiling\ProfilerContext\NullProfilerContext;

trait ProfilerContextAwareTrait /* implements ProfilerContextAware */
{
    private ?ProfilerContext $profilerContext = null;

    /**
     * Set the profiler context.
     */
    public function setProfilerContext(ProfilerContext $profilerContext): void
    {
        $this->profilerContext = $profilerContext;
    }

    /**
     * Get the profiler context.
     */
    protected function getContextProfiler(): ProfilerContext
    {
        return $this->profilerContext ?? ($this->profilerContext = new NullProfilerContext());
    }

    /**
     * Start a new top-level profiler.
     */
    protected function startProfiler(?string $name = null): Profiler
    {
        return $this->getContextProfiler()->start($name);
    }
}
