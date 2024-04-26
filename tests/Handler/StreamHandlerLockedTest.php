<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Handler;

use MakinaCorpus\Profiling\TraceHandler;
use MakinaCorpus\Profiling\Handler\StreamHandler;

class StreamHandlerLockedTest extends StreamHandlerTest
{
    #[\Override]
    protected function createHandler(): TraceHandler
    {
        return new StreamHandler($this->fileUrl, 0644, true);
    }
}
