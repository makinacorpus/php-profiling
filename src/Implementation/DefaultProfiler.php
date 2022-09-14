<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Implementation;

use MakinaCorpus\Profiling\Profiler;

/**
 * Default timer implementation.
 *
 * In order to add additional logic, please use a decorator instead
 * of messing with this class internals.
 */
final class DefaultProfiler implements Profiler
{
    private string $id;
    private ?string $name;
    private int $startingMemory;
    private ?int $consumedMemory = null;
    private float $startedAt;
    private ?float $duration = null;
    private bool $started = false;

    private ?Profiler $parent = null;
    /** @var Profiler[] */
    private array $children = [];

    /** @var callable[] */
    private array $onStart = [];
    /** @var callable[] */
    private array $onStop = [];

    private ?string $description = null;
    /** @var string[] */
    private array $channels = [];
    /** @var array<string, mixed> */
    private array $attributes = [];

    public function __construct(
        ?string $name = null,
        ?Profiler $parent = null,
        ?array $channels = null
    ) {
        $this->id = self::generateUniqueId();
        $this->name = $name;
        $this->parent = $parent;

        if ($channels) {
            $this->channels = $channels;
        }
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
        // 8-hex totally random string - 16^8 > 4 billions possibilites.
        return \bin2hex(\random_bytes(4));
    }

    /**
     * Set on start method.
     */
    public function addStartCallback(callable $callback): self
    {
        $this->onStart[] = $callback;

        return $this;
    }

    public function addStopCallback(callable $callback): self
    {
        $this->onStop[] = $callback;

        return $this;
    }

    /**
     * Really start timer.
     */
    public function execute(): self
    {
        if ($this->started) {
            return $this;
        }

        foreach ($this->onStart as $callback) {
            $callback($this);
        }

        $this->startingMemory = \memory_get_usage();
        $this->startedAt = \hrtime(true);
        $this->started = true;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function start(?string $name = null): Profiler
    {
        if (null !== $this->duration) {
            return new NullProfiler();
        }

        // @todo this will consume time and memory...
        $child = new DefaultProfiler($name, $this, $this->channels);
        foreach ($this->onStart as $callback) {
            $child->addStartCallback($callback);
        }
        foreach ($this->onStop as $callback) {
            $child->addStopCallback($callback);
        }

        $this->children[] = $child;

        return $child->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function stop(?string $name = null): float
    {
        if (!$this->started) {
            return 0.0;
        }

        if (null !== $name) {
            $elapsedTime = 0.0;
            foreach ($this->children as $profiler) {
                if ($profiler->getName() === $name) {
                    // This is probably wrong.
                    $elapsedTime += $profiler->stop();
                }
            }

            return $elapsedTime;
        }

        if (null === $this->duration) {
            $this->consumedMemory = \memory_get_usage() - $this->startingMemory;
            $this->duration = self::nsecToMsec(\hrtime(true) - $this->startedAt);

            foreach ($this->onStop as $callback) {
                $callback($this);
            }

            foreach ($this->children as $profiler) {
                $profiler->stop();
            }
        }

        return $this->duration;
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        return $this->started && null === $this->duration;
    }

    /**
     * {@inheritdoc}
     */
    public function getMemoryUsageStart(): int
    {
        return $this->started ? $this->startingMemory : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getMemoryUsage(): int
    {
        return $this->started ? (null === $this->consumedMemory ? (\memory_get_usage() - $this->startingMemory) : $this->consumedMemory) : 0;
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
        if (!$this->started || null === $this->parent) {
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
        return $this->started ? (null === $this->duration ? self::nsecToMsec(\hrtime(true) - $this->startedAt) : $this->duration) : 0.0;
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
    public function getChannels(): array
    {
        return $this->channels;
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
