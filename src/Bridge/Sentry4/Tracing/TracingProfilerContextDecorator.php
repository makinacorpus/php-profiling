<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Sentry4\Tracing;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerContext;
use Sentry\State\HubInterface;

final class TracingProfilerContextDecorator implements ProfilerContext
{
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
    public function start(?string $name = null): Profiler
    {
        return new TracingProfilerDecorator($this->hub->getTransaction(), $this->decorated->start($name), 0);
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
