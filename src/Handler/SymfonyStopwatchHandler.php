<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Handler;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerTrace;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Composer require symfony/stopwatch:>=5
 */
class SymfonyStopwatchHandler extends AbstractHandler
{
    private Stopwatch $stopwatch;
    private array $started = [];

    public function __construct(Stopwatch $stopwatch)
    {
        $this->stopwatch = $stopwatch;
    }

    private function getTraceId(ProfilerTrace $trace): string
    {
        return $trace->getAbsoluteName() . '/' . $trace->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function onStart(Profiler $profiler): void
    {
        $name = $this->getTraceId($profiler);

        $this->stopwatch->start($name);
        $this->started[$name] = true;
    }

    /**
     * {@inheritdoc}
     */
    public function onStop(ProfilerTrace $trace): void
    {
        $name = $this->getTraceId($trace);

        try {
            $this->stopwatch->stop($name);
        } catch (\LogicException $e) {
            // Unstarted or stopped twice.
        }
        unset($this->started[$name]);
    }

    /**
     * Flush any remaining buffer.
     */
    public function flush(): void
    {
        foreach ($this->started as $name => $enabled) {
            if ($enabled) {
                try {
                    $this->stopwatch->stop($name);
                } catch (\LogicException $e) {
                    // Unstarted or stopped twice.
                }
            }
        }

        $this->started = [];
    }
}
