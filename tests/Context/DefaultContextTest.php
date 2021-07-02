<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Context;

use MakinaCorpus\Profiling\Implementation\DefaultProfilerContext;
use PHPUnit\Framework\TestCase;

final class DefaultContextTest extends TestCase
{
    public function testIsRunning(): void
    {
        $context = new DefaultProfilerContext();
        self::assertFalse($context->isRunning());

        $context->start();
        self::assertTrue($context->isRunning());

        $context->flush();
        self::assertFalse($context->isRunning());
    }

    public function testFlush(): void
    {
        $context = new DefaultProfilerContext();
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
