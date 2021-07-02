<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

interface Profiler extends ProfilerFactory
{
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
     * Get starting point from parent, in milliseconds.
     */
    public function getRelativeStartTime(): float;

    /**
     * Get starting point from root, in milliseconds.
     */
    public function getAbsoluteStartTime(): float;

    /**
     * Get elapsed so far if running, or total time if stopped, in milliseconds.
     */
    public function getElapsedTime(): float;

    /**
     * Get a random unique generated identifier for this timer.
     */
    public function getId(): string;

    /**
     * Get this instance name, return the generated identifier if none was set.
     */
    public function getName(): string;

    /**
     * Get an absolute name including parent items, separator is "/" please
     * avoid using this character into your profiler names.
     */
    public function getAbsoluteName(): string;

    /**
     * Get all children profilers.
     *
     * @return Profiler[]
     */
    public function getChildren(): iterable;
}
