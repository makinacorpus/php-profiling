<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Timer\Handler;

use MakinaCorpus\Profiling\Timer;
use MakinaCorpus\Profiling\Timer\TimerTrace;

/**
 * Used for timer trace output.
 *
 * All timer traces that will go throught will have ended.
 */
interface TraceHandler
{
    /**
     * Set threshold from which traces will be logged.
     */
    public function setThreshold(?int $memoryThreshold, ?float $timeThreshold): void;

    /**
     * Handler timer start.
     */
    public function onStart(Timer $timer): void;

    /**
     * Handle timer stop.
     */
    public function onStop(TimerTrace $trace): void;

    /**
     * Flush any remaining buffer.
     */
    public function flush(): void;
}
