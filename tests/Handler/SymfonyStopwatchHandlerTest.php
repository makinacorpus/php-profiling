<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Handler;

use MakinaCorpus\Profiling\TraceHandler;
use MakinaCorpus\Profiling\Handler\SymfonyStopwatchHandler;
use Symfony\Component\Stopwatch\Stopwatch;

class SymfonyStopwatchHandlerTest extends AbstractHandlerTest
{
    #[\Override]
    protected function createHandler(): TraceHandler
    {
        return new SymfonyStopwatchHandler(
            new Stopwatch()
        );
    }
}
