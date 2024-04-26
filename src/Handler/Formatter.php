<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Handler;

use MakinaCorpus\Profiling\TimerTrace;

interface Formatter
{
    /**
     * Format timer trace for output.
     */
    public function format(TimerTrace $trace): string;
}
