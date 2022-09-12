<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Implementation;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\TraceHandler;

/**
 * Emits profiler traces into registered trace handlers.
 */
final class TracingProfilerDecorator extends AbstractProfilerDecorator
{
    /** @var TraceHandler[] */
    private array $handlers;

    /** @param TraceHandler[] $handlers */
    public function __construct(Profiler $decorated, array $handlers)
    {
        parent::__construct($decorated);

        $this->handlers = $handlers;

        // This will take time and memory within the recorded timing.
        // @todo Find a way to delay real start.
        foreach ($this->handlers as $handler) {
            \assert($handler instanceof TraceHandler);
            $handler->onStart($this);
        }

        $this->handlers = $handlers;
    }

    /**
     * {@inheritdoc}
     */
    public function start(?string $name = null): Profiler
    {
        // @todo This is not right, we do not decorate children.
        return new TracingProfilerDecorator($this->decorated->start($name), $this->handlers);
    }

    /**
     * {@inheritdoc}
     */
    public function stop(?string $name = null): float
    {
        if (!$this->decorated->isRunning()) {
            return $this->decorated->getElapsedTime();
        }

        $ret = $this->decorated->stop($name);

        foreach ($this->handlers as $handler) {
            \assert($handler instanceof TraceHandler);
            $handler->onStop($this);
        }

        return $ret;
    }
}
