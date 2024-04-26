<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Timer;

use MakinaCorpus\Profiling\Timer;

/**
 * @codeCoverageIgnore
 */
final class NullTimer implements Timer
{
    #[\Override]
    public function addStartCallback(callable $callback): Timer
    {
        return $this;
    }

    #[\Override]
    public function addStopCallback(callable $callback): Timer
    {
        return $this;
    }

    #[\Override]
    public function execute(): Timer
    {
        return $this;
    }

    #[\Override]
    public function start(?string $name = null): Timer
    {
        return $this;
    }

    #[\Override]
    public function stop(?string $name = null): void
    {
    }

    #[\Override]
    public function isRunning(): bool
    {
        return false;
    }

    #[\Override]
    public function getMemoryUsageStart(): int
    {
        return 0;
    }

    #[\Override]
    public function getMemoryUsage(): int
    {
        return 0;
    }

    #[\Override]
    public function getRelativeStartTime(): float
    {
        return 0.0;
    }

    #[\Override]
    public function getAbsoluteStartTime(): float
    {
        return 0.0;
    }

    #[\Override]
    public function getElapsedTime(): float
    {
        return 0.0;
    }

    #[\Override]
    public function getElapsedTimeNano(): float
    {
        return 0.0;
    }

    #[\Override]
    public function getId(): string
    {
        return '(null)';
    }

    #[\Override]
    public function getName(): string
    {
        return '(null)';
    }

    #[\Override]
    public function getAbsoluteName(): string
    {
        return '(null)';
    }

    #[\Override]
    public function getChildren(): iterable
    {
        return [];
    }

    #[\Override]
    public function setDescription(string $description): void
    {
    }

    #[\Override]
    public function getDescription(): ?string
    {
        return null;
    }

    #[\Override]
    public function getChannels(): array
    {
        return [];
    }

    #[\Override]
    public function setAttribute(string $name, $value): void
    {
    }

    #[\Override]
    public function getAttributes(): array
    {
        return [];
    }
}
