<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Handler;

use MakinaCorpus\Profiling\TraceHandler;
use MakinaCorpus\Profiling\Handler\SentryHandler;
use Sentry\State\Hub;

class SentryHandlerTest extends AbstractHandlerTest
{
    #[\Override]
    protected function createHandler(): TraceHandler
    {
        return new SentryHandler(
            new Hub()
        );
    }
}
