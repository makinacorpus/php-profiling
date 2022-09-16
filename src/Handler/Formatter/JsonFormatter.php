<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Handler\Formatter;

use MakinaCorpus\Profiling\ProfilerTrace;
use MakinaCorpus\Profiling\Handler\Formatter;

class JsonFormatter implements Formatter
{
    private ?int $pid = null;
    private bool $started = false;

    /**
     * {@inheritdoc}
     */
    public function format(ProfilerTrace $trace): string
    {
        if (!$this->started) {
            $this->started = true;
        }

        $elapsedTime = $trace->getElapsedTime();
        $consumedMemory = $trace->getMemoryUsage();

        return \json_encode([
             'pid' => $this->pid ?? ($this->pid = \getmypid()),
             'id' => $trace->getId(),
             'name' => $trace->getAbsoluteName(),
             'relname' => $trace->getName(),
             'timems' => $elapsedTime,
             'timenano' => $trace->getElapsedTime(),
             'membytes' => $consumedMemory,
             'childcount' => \count($trace->getChildren()),
        ]);
    }
}
