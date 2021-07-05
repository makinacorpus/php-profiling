<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

interface ProfilerContext extends ProfilerFactory
{
    /**
     * Enable or disable profiling.
     *
     * It won't shut down currently running profilers, it will just prevent
     * new profilers creation.
     */
    public function toggle(bool $enabled): void;

    /**
     * Is this context enabled, if disabled, any start attempt will result in a
     * null instance given instead.
     */
    public function isEnabled(): bool;

    /**
     * Is there at least one profiler running.
     */
    public function isRunning(): bool;

    /**
     * Get all currently registered profilers.
     *
     * @return Profiler[]
     */
    public function getAllProfilers(): iterable;

    /**
     * Stop all profilers, remove all, then return them.
     *
     * It frees all consumed memory so far, it can be called only once.
     *
     * @return Profiler[]
     */
    public function flush(): iterable;
}
