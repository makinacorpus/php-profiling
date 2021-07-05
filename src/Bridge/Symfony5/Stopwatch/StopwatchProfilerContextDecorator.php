<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony5\Stopwatch;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerContext;
use MakinaCorpus\Profiling\Implementation\NullProfiler;
use Symfony\Component\Stopwatch\Stopwatch;

final class StopwatchProfilerContextDecorator implements ProfilerContext
{
    private bool $enabled = true;
    private ProfilerContext $decorated;
    private Stopwatch $stopwatch;

    public function __construct(Stopwatch $stopwatch, ProfilerContext $decorated)
    {
        $this->decorated = $decorated;
        $this->stopwatch = $stopwatch;
    }

    /**
     * {@inheritdoc}
     */
    public function toggle(bool $enabled): void
    {
        $this->decorated->toggle($enabled);
        $this->enabled = $enabled;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * {@inheritdoc}
     */
    public function start(?string $name = null): Profiler
    {
        if ($this->enabled) {
            return new StopwatchProfilerDecorator($this->stopwatch, $this->decorated->start($name));
        } else {
            return new NullProfiler();
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
    public function getAllProfilers(): iterable
    {
        // @todo Does not return the decorated instances.
        return $this->decorated->getAllProfilers();
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): iterable
    {
        $ret = $this->decorated->flush();

        $this->stopwatch->reset();

        return $ret;
    }
}
