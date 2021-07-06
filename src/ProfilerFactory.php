<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

interface ProfilerFactory
{
    /**
     * Start new profiler in this context.
     *
     * In case the current context or profiler was closed or flushed, this
     * will return a null instance.
     */
    public function start(?string $name = null): Profiler;
}
