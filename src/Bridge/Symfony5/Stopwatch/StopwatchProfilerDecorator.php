<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony5\Stopwatch;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\Implementation\AbstractProfilerDecorator;
use Symfony\Component\Stopwatch\Stopwatch;

final class StopwatchProfilerDecorator extends AbstractProfilerDecorator
{
    private Stopwatch $stopwatch;

    public function __construct(Stopwatch $stopwatch, Profiler $decorated)
    {
        parent::__construct($decorated);

        $this->stopwatch = $stopwatch;
        $this->stopwatch->start($this->decorated->getAbsoluteName());
    }

    /**
     * {@inheritdoc}
     */
    protected function createDecorator(Profiler $decorated, ?string $name = null): self
    {
        return new StopwatchProfilerDecorator($this->stopwatch, $this->decorated->start($name));
    }

    /**
     * Stop decorated instance.
     */
    protected function doStop(): void
    {
        $this->stopwatch->stop($this->decorated->getAbsoluteName());
    }
}
