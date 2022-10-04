<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\ProfilerContext;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerContext;
use MakinaCorpus\Profiling\Profiler\NullProfiler;

/**
 * @codeCoverageIgnore
 */
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
    public function create(?string $name = null, ?array $channels = null): Profiler
    {
        return new NullProfiler();
    }

    /**
     * {@inheritdoc}
     */
    public function start(?string $name = null, ?array $channels = null): Profiler
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
     *
     * @deprecated
     *   Will be removed in next major.
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
        return [];
    }
}
