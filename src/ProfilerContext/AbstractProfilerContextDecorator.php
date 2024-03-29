<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\ProfilerContext;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerContext;

abstract class AbstractProfilerContextDecorator implements ProfilerContext
{
    protected ProfilerContext $decorated;

    public function __construct(ProfilerContext $decorated)
    {
        $this->decorated = $decorated;
    }

    /**
     * {@inheritdoc}
     */
    public function toggle(bool $enabled): void
    {
        $this->decorated->toggle($enabled);
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return $this->decorated->isEnabled();
    }

    /**
     * {@inheritdoc}
     */
    public function create(?string $name = null, ?array $channels = null): Profiler
    {
        return $this->decorated->create($name, $channels);
    }

    /**
     * {@inheritdoc}
     */
    public function start(?string $name = null, ?array $channels = null): Profiler
    {
        return $this->create($name, $channels)->execute();
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
     *
     * @deprecated
     *   Will be removed in next major.
     */
    public function getAllProfilers(): iterable
    {
        return $this->decorated->getAllProfilers();
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): iterable
    {
        return $this->decorated->flush();
    }
}
