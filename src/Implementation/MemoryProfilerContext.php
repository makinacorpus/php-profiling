<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Implementation;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerContext;

/**
 * Default implementation that keeps everything into memory.
 *
 * This is a dangerous implementation to use, if it isn't being flushed
 * regularly you will experience memory leaks, especially when running
 * batches in CLI.
 */
final class MemoryProfilerContext implements ProfilerContext
{
    private bool $enabled = true;
    /** @var Profiler */
    private array $profilers = [];

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
            return $this->profilers[] = new DefaultProfiler($name, null, $channels);
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
        return !empty($this->profilers);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated
     *   Will be removed in next major.
     */
    public function getAllProfilers(): iterable
    {
        return $this->profilers;
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): iterable
    {
        $ret = $this->profilers;
        // Early reset, if later PHP becomes asynchronous, the context instance
        // can start being re-used right up now, while profilers are being
        // stopped in the background.
        $this->profilers = [];

        foreach ($ret as $profiler) {
            $profiler->stop();
        }

        return $ret;
    }
}
