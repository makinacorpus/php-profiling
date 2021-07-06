<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Implementation;

use MakinaCorpus\Profiling\Profiler;

abstract class AbstractProfilerDecorator implements Profiler
{
    protected Profiler $decorated;
    /** @var self[] */
    protected array $children = [];

    public function __construct(Profiler $decorated)
    {
        $this->decorated = $decorated;
    }

    /**
     * Necessary for implementing correctly the start() method.
     */
    protected abstract function createDecorator(Profiler $decorated, ?string $name = null): self;

    /**
     * Stop this instance. Decorated instance is already stopped.
     */
    protected function doStop(): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function start(?string $name = null): Profiler
    {
        if (!$this->decorated->isRunning()) {
            return new NullProfiler();
        }

        return $this->children[] = $this->createDecorator($this->decorated->start($name), $name);
    }

    /**
     * {@inheritdoc}
     *
     * Due to the API design, we cannot inject decorated instances to the
     * decorated instance, so we have to actually re-implement the stop()
     * method.
     *
     * This is basically the exact same as the DefaultProfiler implementation
     * with the doStop() call added at the right place.
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
        } else if ($this->decorated->isRunning()) {
            foreach ($this->children as $profiler) {
                $profiler->stop();
            }
            $this->decorated->stop();
            $this->doStop();
            return $this->decorated->getElapsedTime();
        } else {
            return $this->decorated->getElapsedTime();
        }
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
    public function getChildren(): iterable
    {
        return $this->children;
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
    public function getAttributes(): array
    {
        return $this->decorated->getAttributes();
    }
}
