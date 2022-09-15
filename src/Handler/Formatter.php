<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Handler;

use MakinaCorpus\Profiling\ProfilerTrace;

interface Formatter
{
    /**
     * Format profiler trace for output.
     */
    public function format(ProfilerTrace $trace): string;
}
