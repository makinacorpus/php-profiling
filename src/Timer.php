<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

interface Timer extends TimerTrace
{
    /**
     * Add on start callback.
     *
     * @param callable $callback
     *   Takes on argument, a Timer instance, return is ignored.
     *
     * @return $this
     */
    public function addStartCallback(callable $callback): Timer;

    /**
     * Add on stop callback.
     *
     * @param callable $callback
     *   Takes on argument, a Timer instance, return is ignored.
     *
     * @return $this
     */
    public function addStopCallback(callable $callback): Timer;

    /**
     * Start timer.
     *
     * @return $this
     */
    public function execute(): Timer;

    /**
     * Create and start new child timer.
     *
     * All on start and stop callbacks are propagated to children.
     *
     * In case the current profiler or timer was closed or flushed, this
     * will return a null instance.
     */
    public function start(?string $name = null): Timer;

    /**
     * End current timer or child timer, and return elasped time, in milliseconds.
     *
     * When a single timer ends, it ends all children.
     *
     * If timer was already closed, this should remain silent and do nothing.
     */
    public function stop(?string $name = null): void;

    /**
     * Is this timer still running.
     */
    public function isRunning(): bool;

    /**
     * Set timer description.
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
