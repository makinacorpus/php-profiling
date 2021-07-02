<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

interface ProfilerFactory
{
    /**
     * Start new profiler in this context.
     *
     * @throws ProfilerClosedError
     *   In case the current context or profiler was closed or flushed.
     */
    public function start(?string $name = null): Profiler;
}
