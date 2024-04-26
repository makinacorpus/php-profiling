<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Handler;

use MakinaCorpus\Profiling\Timer;
use MakinaCorpus\Profiling\TimerTrace;
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

    private function getTraceId(TimerTrace $trace): string
    {
        return $trace->getAbsoluteName() . '/' . $trace->getId();
    }

    #[\Override]
    public function onStart(Timer $timer): void
    {
        $name = $this->getTraceId($timer);

        $this->stopwatch->start($name);
        $this->started[$name] = true;
    }

    #[\Override]
    public function onStop(TimerTrace $trace): void
    {
        $name = $this->getTraceId($trace);

        try {
            $this->stopwatch->stop($name);
        } catch (\LogicException $e) {
            // Unstarted or stopped twice.
        }
        unset($this->started[$name]);
    }

    #[\Override]
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
