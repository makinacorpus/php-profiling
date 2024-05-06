<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Timer\Handler;

use MakinaCorpus\Profiling\Timer\TimerTrace;

interface Formatter
{
    /**
     * Format timer trace for output.
     */
    public function format(TimerTrace $trace): string;
}
