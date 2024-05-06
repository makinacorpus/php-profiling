<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Timer\Handler;

use MakinaCorpus\Profiling\Timer\Handler\SentryHandler;
use MakinaCorpus\Profiling\Timer\Handler\TraceHandler;
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
