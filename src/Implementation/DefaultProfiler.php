<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Implementation;

use MakinaCorpus\Profiling\Profiler;

/**
 * Default timer implementation: the one you'll ever need.
 */
final class DefaultProfiler implements Profiler
{
    private string $id;
    private ?string $name;
    private int $startingMemory;
    private ?int $consumedMemory = null;
    private float $startedAt;
    private ?float $duration = null;

    private ?Profiler $parent = null;
    /** @var Profiler[] */
    private array $children = [];

    private ?string $description = null;
    /** @var array<string, mixed> */
    private array $attributes = [];

    public function __construct(?string $name = null, ?Profiler $parent = null)
    {
        $this->parent = $parent;
        $this->id = self::generateUniqueId();
        $this->name = $name;
        $this->startingMemory = \memory_get_usage();
        $this->startedAt = \hrtime(true);
    }

    /**
     * Convert nano seconds to milliseconds.
     */
    public static function nsecToMsec(float $nsec): float
    {
        return $nsec / 1e+6;
    }

    /**
     * Generate a random unique identifier.
     */
    public static function generateUniqueId(): string
    {
        return \implode('', \array_rand(\array_flip(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f']), 7));
    }

    /**
     * {@inheritdoc}
     */
    public function start(?string $name = null): Profiler
    {
        if (null !== $this->duration) {
            return new NullProfiler();
        }

        return $this->children[] = new DefaultProfiler($name, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function stop(?string $name = null): float
    {
        if (null !== $name) {
            $elapsedTime = 0.0;
            foreach ($this->children as $profiler) {
                if ($profiler->getName() === $name) {
                    // This is probably wrong.
                    $elapsedTime += $profiler->stop();
                }
            }
            return $elapsedTime;
        } else if (null === $this->duration) {
            $this->consumedMemory = \memory_get_usage() - $this->startingMemory;
            foreach ($this->children as $profiler) {
                $profiler->stop();
            }
            return $this->duration = self::nsecToMsec(\hrtime(true) - $this->startedAt);
        } else {
            return $this->duration;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        return null === $this->duration;
    }

    /**
     * {@inheritdoc}
     */
    public function getMemoryUsageStart(): int
    {
        return $this->startingMemory;
    }

    /**
     * {@inheritdoc}
     */
    public function getMemoryUsage(): int
    {
        return null === $this->consumedMemory ? (\memory_get_usage() - $this->startingMemory) : $this->consumedMemory;
    }

    /**
     * {@inheritdoc}
     */
    public function getRelativeStartTime(): float
    {
        if (null === $this->parent) {
            return 0.0;
        }

        // @todo Need a way to propagate hrtime from parent.
        throw new \Exception("Not implemented yet.");
    }

    /**
     * {@inheritdoc}
     */
    public function getAbsoluteStartTime(): float
    {
        if (null === $this->parent) {
            return 0.0;
        }

        // @todo Need a way to propagate hrtime from parent.
        throw new \Exception("Not implemented yet.");
    }

    /**
     * Get elapsed so far if running, or total time if stopped, in milliseconds.
     */
    public function getElapsedTime(): float
    {
        return null === $this->duration ? self::nsecToMsec(\hrtime(true) - $this->startedAt) : $this->duration;
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name ?? $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getAbsoluteName(): string
    {
        return (null === $this->parent) ? $this->getName() : ($this->parent->getAbsoluteName() . '/' . $this->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function getChildren(): iterable
    {
        return $this->children;
    }

    /**
     * {@inheritdoc}
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
