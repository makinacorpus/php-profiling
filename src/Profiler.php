<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

interface Profiler extends ProfilerTrace
{
    /**
     * Start new child profiler.
     *
     * In case the current context or profiler was closed or flushed, this
     * will return a null instance.
     */
    public function start(?string $name = null): Profiler;

    /**
     * End current timer or child timer, and return elasped time, in milliseconds.
     *
     * When a single timer ends, it ends all children.
     *
     * If timer was already closed, this should remain silent and do nothing.
     */
    public function stop(?string $name = null): float;

    /**
     * Is this profiler still running.
     */
    public function isRunning(): bool;

    /**
     * Set profiler description.
     *
     * Description is a purely informational human readable string.
     */
    public function setDescription(string $description): void;

    /**
     * Set arbitrary attribute.
     *
     * @param mixed $value
     *   Any value. You are discouraged from using attributes too much as it
     *   will grow the memory consumption.
     */
    public function setAttribute(string $name, $value): void;
}
