<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

use MakinaCorpus\Profiling\Profiler\DefaultProfiler;

trait ProfilerAwareTrait /* implements ProfilerAware */
{
    private ?Profiler $profiler = null;

    /**
     * Set the profiler.
     */
    public function setProfiler(Profiler $profiler): void
    {
        $this->profiler = $profiler;
    }

    /**
     * Get the profiler.
     */
    protected function getProfiler(): Profiler
    {
        return $this->profiler ??= new DefaultProfiler();
    }

    /**
     * Start a new top-level timer.
     */
    protected function startTimer(?string $name = null): Timer
    {
        return $this->getProfiler()->timer($name);
    }
}
