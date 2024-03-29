<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

interface Profiler extends ProfilerTrace
{
    /**
     * Add on start callback.
     *
     * @param callable $callback
     *   Takes on argument, a Profiler instance, return is ignored.
     *
     * @return $this
     */
    public function addStartCallback(callable $callback): Profiler;

    /**
     * Add on stop callback.
     *
     * @param callable $callback
     *   Takes on argument, a Profiler instance, return is ignored.
     *
     * @return $this
     */
    public function addStopCallback(callable $callback): Profiler;

    /**
     * Start timer.
     *
     * @return $this
     */
    public function execute(): Profiler;

    /**
     * Create and start new child profiler.
     *
     * All on start and stop callbacks are propagated to children.
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
    public function stop(?string $name = null): void;

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
