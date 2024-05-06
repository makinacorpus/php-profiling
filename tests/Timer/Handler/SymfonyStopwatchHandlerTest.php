<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Timer\Handler;

use MakinaCorpus\Profiling\Timer\Handler\SymfonyStopwatchHandler;
use MakinaCorpus\Profiling\Timer\Handler\TraceHandler;
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
