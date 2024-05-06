<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Timer\Handler;

use MakinaCorpus\Profiling\Timer\Handler\StreamHandler;
use MakinaCorpus\Profiling\Timer\Handler\TraceHandler;

class StreamHandlerLockedTest extends StreamHandlerTest
{
    #[\Override]
    protected function createHandler(): TraceHandler
    {
        return new StreamHandler($this->fileUrl, 0644, true);
    }
}
