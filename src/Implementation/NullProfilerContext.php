<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Implementation;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerContext;

final class NullProfilerContext implements ProfilerContext
{
    /**
     * {@inheritdoc}
     */
    public function toggle(bool $enabled): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function start(?string $name = null): Profiler
    {
        return new NullProfiler();
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllProfilers(): iterable
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): iterable
    {
    }
}
