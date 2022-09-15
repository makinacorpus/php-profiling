<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\ProfilerContext;

use MakinaCorpus\Profiling\ProfilerContext\MemoryProfilerContext;
use MakinaCorpus\Profiling\ProfilerContext\TracingProfilerContextDecorator;
use PHPUnit\Framework\TestCase;

final class DefaultProfilerContextTest extends TestCase
{
    public static function getProfilerContexts()
    {
        yield [new MemoryProfilerContext()];
        yield [new TracingProfilerContextDecorator(
            new MemoryProfilerContext(),
            [],
            []
        )];
    }

    public function testIsRunning(): void
    {
        $context = new MemoryProfilerContext();
        self::assertFalse($context->isRunning());

        $context->start();
        self::assertTrue($context->isRunning());

        $context->flush();
        self::assertFalse($context->isRunning());
    }

    public function testFlush(): void
    {
        $context = new MemoryProfilerContext();
        $profiler1 = $context->start('one');
        $profiler2 = $context->start('two');

        self::assertTrue($profiler1->isRunning());
        self::assertTrue($profiler2->isRunning());

        $ret = $context->flush();

        self::assertFalse($profiler1->isRunning());
        self::assertFalse($profiler2->isRunning());

        self::assertCount(2, $ret);
        self::assertSame($ret[0], $profiler1);
        self::assertSame($ret[1], $profiler2);
    }
}
