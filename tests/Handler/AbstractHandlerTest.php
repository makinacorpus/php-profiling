<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Handler;

use MakinaCorpus\Profiling\TraceHandler;
use MakinaCorpus\Profiling\ProfilerContext\MemoryProfilerContext;
use PHPUnit\Framework\TestCase;

abstract class AbstractHandlerTest extends TestCase
{
    protected abstract function createHandler(): TraceHandler;

    public function testStart(): void
    {
        $handler = $this->createHandler();

        $profiler = (new MemoryProfilerContext())->start('foo'); 

        $handler->onStart($profiler);
        $profiler->stop();
        $handler->onStop($profiler);

        // self::expectNotToPerformAssertions() disable coverage.
        self::assertTrue(true);
    }

    public function testStopTwiceDontCrash(): void
    {
        $handler = $this->createHandler();

        $profiler = (new MemoryProfilerContext())->start('foo'); 

        $handler->onStart($profiler);
        $profiler->stop();
        $handler->onStop($profiler);

        $profiler->stop();
        $handler->onStop($profiler);

        // self::expectNotToPerformAssertions() disable coverage.
        self::assertTrue(true);
    }

    public function testFlush(): void
    {
        $handler = $this->createHandler();

        $profiler = (new MemoryProfilerContext())->start('foo'); 

        $handler->onStart($profiler);
        $handler->flush();

        // self::expectNotToPerformAssertions() disable coverage.
        self::assertTrue(true);
    }

    public function testStopAfterFlushDontCrash(): void
    {
        $handler = $this->createHandler();

        $profiler = (new MemoryProfilerContext())->start('foo'); 

        $handler->onStart($profiler);
        $profiler->stop();
        $handler->onStop($profiler);

        $profiler->stop();

        // self::expectNotToPerformAssertions() disable coverage.
        self::assertTrue(true);
    }

    public function testStopNonExistingDontCrash(): void
    {
        $handler = $this->createHandler();

        $profiler = (new MemoryProfilerContext())->start('foo'); 

        $profiler->stop();
        $handler->onStop($profiler);

        // self::expectNotToPerformAssertions() disable coverage.
        self::assertTrue(true);
    }
}
