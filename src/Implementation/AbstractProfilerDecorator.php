<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Implementation;

use MakinaCorpus\Profiling\Profiler;

abstract class AbstractProfilerDecorator implements Profiler
{
    protected Profiler $decorated;

    public function __construct(Profiler $decorated)
    {
        $this->decorated = $decorated;
    }

    /**
     * {@inheritdoc}
     */
    public function start(?string $name = null): Profiler
    {
        return $this->decorated->start($name);
    }

    /**
     * {@inheritdoc}
     */
    public function stop(?string $name = null): float
    {
        return $this->decorated->stop($name);
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        return $this->decorated->isRunning();
    }

    /**
     * {@inheritdoc}
     */
    public function getChildren(): iterable
    {
        return $this->decorated->getChildren();
    }

    /**
     * {@inheritdoc}
     */
    public function setDescription(string $description): void
    {
        $this->decorated->setDescription($description);
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute(string $name, $value): void
    {
        $this->decorated->setAttribute($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getMemoryUsageStart(): int
    {
        return $this->decorated->getMemoryUsageStart();
    }

    /**
     * {@inheritdoc}
     */
    public function getMemoryUsage(): int
    {
        return $this->decorated->getMemoryUsage();
    }

    /**
     * {@inheritdoc}
     */
    public function getRelativeStartTime(): float
    {
        return $this->decorated->getRelativeStartTime();
    }

    /**
     * {@inheritdoc}
     */
    public function getAbsoluteStartTime(): float
    {
        return $this->decorated->getAbsoluteStartTime();
    }

    /**
     * {@inheritdoc}
     */
    public function getElapsedTime(): float
    {
        return $this->decorated->getElapsedTime();
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this->decorated->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->decorated->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getAbsoluteName(): string
    {
        return $this->decorated->getAbsoluteName();
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): ?string
    {
        return $this->decorated->getDescription();
    }

    /**
     * {@inheritdoc}
     */
    public function getChannels(): array
    {
        return $this->decorated->getChannels();
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes(): array
    {
        return $this->decorated->getAttributes();
    }
}
