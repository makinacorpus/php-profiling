<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Handler;

use MakinaCorpus\Profiling\Profiler\DefaultProfiler;
use MakinaCorpus\Profiling\TraceHandler;
use PHPUnit\Framework\TestCase;

abstract class AbstractHandlerTest extends TestCase
{
    protected abstract function createHandler(): TraceHandler;

    public function testStart(): void
    {
        $handler = $this->createHandler();

        $timer = (new DefaultProfiler())->timer('foo'); 

        $handler->onStart($timer);
        $timer->stop();
        $handler->onStop($timer);

        // self::expectNotToPerformAssertions() disable coverage.
        self::assertTrue(true);
    }

    public function testStopTwiceDontCrash(): void
    {
        $handler = $this->createHandler();

        $timer = (new DefaultProfiler())->timer('foo'); 

        $handler->onStart($timer);
        $timer->stop();
        $handler->onStop($timer);

        $timer->stop();
        $handler->onStop($timer);

        // self::expectNotToPerformAssertions() disable coverage.
        self::assertTrue(true);
    }

    public function testFlush(): void
    {
        $handler = $this->createHandler();

        $timer = (new DefaultProfiler())->timer('foo'); 

        $handler->onStart($timer);
        $handler->flush();

        // self::expectNotToPerformAssertions() disable coverage.
        self::assertTrue(true);
    }

    public function testStopAfterFlushDontCrash(): void
    {
        $handler = $this->createHandler();

        $timer = (new DefaultProfiler())->timer('foo'); 

        $handler->onStart($timer);
        $timer->stop();
        $handler->onStop($timer);

        $timer->stop();

        // self::expectNotToPerformAssertions() disable coverage.
        self::assertTrue(true);
    }

    public function testStopNonExistingDontCrash(): void
    {
        $handler = $this->createHandler();

        $timer = (new DefaultProfiler())->timer('foo'); 

        $timer->stop();
        $handler->onStop($timer);

        // self::expectNotToPerformAssertions() disable coverage.
        self::assertTrue(true);
    }
}
