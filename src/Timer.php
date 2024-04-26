<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling;

class Timer implements TimerTrace
{
    private ?string $id = null;
    private ?string $name;
    private int $startingMemory;
    private ?int $consumedMemory = null;
    private float $startedAt;
    private ?float $duration = null;
    private ?float $durationNano = null;
    private bool $started = false;
    private bool $running = false;

    private ?string $absoluteName = null;
    private ?Timer $parent = null;
    /** @var Timer[] */
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
        ?Timer $parent = null,
        ?array $channels = null
    ) {
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
     * Add on start callback.
     *
     * @param callable $callback
     *   Takes on argument, a Timer instance, return is ignored.
     *
     * @return $this
     */
    public function addStartCallback(callable $callback): Timer
    {
        $this->onStart[] = $callback;

        return $this;
    }

    /**
     * Add on stop callback.
     *
     * @param callable $callback
     *   Takes on argument, a Timer instance, return is ignored.
     *
     * @return $this
     */
    public function addStopCallback(callable $callback): Timer
    {
        $this->onStop[] = $callback;

        return $this;
    }

    /**
     * Start timer.
     *
     * @return $this
     */
    public function execute(): Timer
    {
        if ($this->started) {
            return $this;
        }

        foreach ($this->onStart as $callback) {
            $callback($this);
        }

        $this->started = true;
        $this->running = true;
        $this->startingMemory = \memory_get_usage();
        $this->startedAt = \hrtime(true);

        return $this;
    }

    /**
     * Create and start new child timer.
     *
     * All on start and stop callbacks are propagated to children.
     *
     * In case the current profiler or timer was closed or flushed, this
     * will return a null instance.
     */
    public function start(?string $name = null): Timer
    {
        if (null !== $this->duration) {
            // Timer was stopped, do not register the new one as a child.
            // Simply return a new instance to avoid crashes when API is
            // misused.
            return new Timer($name);
        }

        // @todo this will consume time and memory...
        $child = new Timer($name, $this, $this->channels);
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
     * End current timer or child timer, and return elasped time, in milliseconds.
     *
     * When a single timer ends, it ends all children.
     *
     * If timer was already closed, this should remain silent and do nothing.
     */
    public function stop(?string $name = null): void
    {
        if (null !== $name) {
            foreach ($this->children as $child) {
                if ($child->getName() === $name) {
                    $child->stop();
                }
            }
            return;
        }

        if (!$this->running) {
            return;
        }

        $this->durationNano = \hrtime(true) - $this->startedAt;
        $this->consumedMemory = \memory_get_usage() - $this->startingMemory;
        $this->duration = self::nsecToMsec($this->durationNano);
        $this->running = false;

        foreach ($this->onStop as $callback) {
            $callback($this);
        }
        foreach ($this->children as $child) {
            $child->stop();
        }
    }

    /**
     * Is this timer still running.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Set timer description.
     *
     * Description is a purely informational human readable string.
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * Set arbitrary attribute.
     *
     * @param mixed $value
     *   Any value. You are discouraged from using attributes too much as it
     *   will grow the memory consumption.
     */
    public function setAttribute(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }

    #[\Override]
    public function getMemoryUsageStart(): int
    {
        return $this->started ? $this->startingMemory : 0;
    }

    #[\Override]
    public function getMemoryUsage(): int
    {
        if (!$this->started) {
            return 0;
        }
        if (null === $this->consumedMemory) {
            return \memory_get_usage() - $this->startingMemory;
        }
        return $this->consumedMemory;
    }

    #[\Override]
    public function getRelativeStartTime(): float
    {
        if (null === $this->parent) {
            return 0.0;
        }

        // @todo Need a way to propagate hrtime from parent.
        throw new \Exception("Not implemented yet.");
    }

    #[\Override]
    public function getAbsoluteStartTime(): float
    {
        if (!$this->started || null === $this->parent) {
            return 0.0;
        }

        // @todo Need a way to propagate hrtime from parent.
        throw new \Exception("Not implemented yet.");
    }

    #[\Override]
    public function getElapsedTime(): float
    {
        if (!$this->started) {
            return 0.0;
        }
        if (null === $this->duration) {
            return self::nsecToMsec(\hrtime(true) - $this->startedAt);
        }
        return $this->duration;
    }

    #[\Override]
    public function getElapsedTimeNano(): float
    {
        if (!$this->started) {
            return 0.0;
        }
        if (null === $this->durationNano) {
            return \hrtime(true) - $this->startedAt;
        }
        return $this->duration;
    }

    #[\Override]
    public function getId(): string
    {
        return $this->id ?? ($this->id = self::generateUniqueId());
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name ?? $this->getId();
    }

    #[\Override]
    public function getAbsoluteName(): string
    {
        if (null === $this->parent) {
            return $this->name ?? $this->getId();
        }
        if (null === $this->absoluteName) {
            $this->absoluteName = $this->parent->getAbsoluteName() . '/' . ($this->name ?? $this->getId());
        }
        return $this->absoluteName;
    }

    #[\Override]
    public function getChildren(): iterable
    {
        return $this->children;
    }

    #[\Override]
    public function getDescription(): ?string
    {
        return $this->description;
    }

    #[\Override]
    public function getChannels(): array
    {
        return $this->channels;
    }

    #[\Override]
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
