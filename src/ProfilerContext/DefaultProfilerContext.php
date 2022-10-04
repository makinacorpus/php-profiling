<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\ProfilerContext;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerContext;
use MakinaCorpus\Profiling\Profiler\DefaultProfiler;
use MakinaCorpus\Profiling\Profiler\NullProfiler;

/**
 * Default implementation, just create profilers.
 *
 * @todo Find a better way to plug this.
 */
final class DefaultProfilerContext implements ProfilerContext
{
    private bool $enabled = true;

    /**
     * {@inheritdoc}
     */
    public function toggle(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * {@inheritdoc}
     */
    public function create(?string $name = null, ?array $channels = null): Profiler
    {
        if ($this->enabled) {
            return new DefaultProfiler($name, null, $channels);
        } else {
            return new NullProfiler();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function start(?string $name = null, ?array $channels = null): Profiler
    {
        return $this->create()->execute();
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
