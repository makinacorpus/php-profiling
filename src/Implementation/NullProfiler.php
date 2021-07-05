<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Implementation;

use MakinaCorpus\Profiling\Profiler;

final class NullProfiler implements Profiler
{
    public function __construct(?string $name = null, ?Profiler $parent = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function start(?string $name = null): Profiler
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function stop(?string $name = null): float
    {
        return 0.0;
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
    public function getRelativeStartTime(): float
    {
        return 0.0;
    }

    /**
     * {@inheritdoc}
     */
    public function getAbsoluteStartTime(): float
    {
        return 0.0;
    }

    /**
     * {@inheritdoc}
     */
    public function getElapsedTime(): float
    {
        return 0.0;
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return '(null)';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return '(null)';
    }

    /**
     * {@inheritdoc}
     */
    public function getAbsoluteName(): string
    {
        return '(null)';
    }

    /**
     * {@inheritdoc}
     */
    public function getChildren(): iterable
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute(string $name, $value): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes(): array
    {
        return [];
    }
}
