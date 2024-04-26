<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Handler;

use MakinaCorpus\Profiling\TraceHandler;
use MakinaCorpus\Profiling\Handler\StreamHandler;

class StreamHandlerTest extends AbstractHandlerTest
{
    protected ?string $fileUrl = null;

    #[\Override]
    protected function setUp(): void
    {
        $this->fileUrl = \sys_get_temp_dir() . '/profiling/' . \uniqid() . '/trace-' . \uniqid();
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->fileUrl) {
            if (\file_exists($this->fileUrl)) {
                \unlink($this->fileUrl);
            }
            if (\is_dir($directory = \dirname($this->fileUrl))) {
                \rmdir($directory);
            }
            if (\is_dir($parentDirectory = \dirname($this->fileUrl, 2))) {
                @\rmdir($parentDirectory);
            }
            $this->fileUrl = null;
        }
    }

    #[\Override]
    protected function createHandler(): TraceHandler
    {
        return new StreamHandler($this->fileUrl);
    }
}
