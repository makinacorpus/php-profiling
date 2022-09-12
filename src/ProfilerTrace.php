<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

/**
 * Represent an ended profiling trace.
 */
interface ProfilerTrace
{
    /**
     * Get starting memory usage.
     */
    public function getMemoryUsageStart(): int;

    /**
     * Get relative memory usage until now.
     */
    public function getMemoryUsage(): int;

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

    /**
     * Get description.
     */
    public function getDescription(): ?string;

    /**
     * Get channels that were programatically added to this profiler.
     */
    public function getChannels(): array;

    /**
     * Get all attributes.
     *
     * @return array<string, mixed>
     *   Keys are attribute names, values are arbitrary values.
     */
    public function getAttributes(): array;
}
