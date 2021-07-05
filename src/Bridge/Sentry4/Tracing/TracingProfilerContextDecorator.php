<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Sentry4\Tracing;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerContext;
use MakinaCorpus\Profiling\Implementation\NullProfiler;
use Sentry\State\HubInterface;

final class TracingProfilerContextDecorator implements ProfilerContext
{
    private bool $enabled = true;
    private ProfilerContext $decorated;
    private HubInterface $hub;

    public function __construct(HubInterface $hub, ProfilerContext $decorated)
    {
        $this->decorated = $decorated;
        $this->hub = $hub;
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
            return new TracingProfilerDecorator($this->hub->getTransaction(), $this->decorated->start($name), 0);
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

        // @todo I don't know sentry API enough yet to be able to
        //   just flush it. Let's consider that it will do it by itself.

        return $ret;
    }
}
