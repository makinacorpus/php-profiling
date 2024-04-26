<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Profiler;

use MakinaCorpus\Profiling\Profiler\DefaultProfiler;
use MakinaCorpus\Profiling\Profiler\TracingProfilerDecorator;
use PHPUnit\Framework\TestCase;

final class DefaultProfilerTest extends TestCase
{
    public static function getProfilers()
    {
        yield [new DefaultProfiler()];
        yield [new TracingProfilerDecorator(
            new DefaultProfiler(),
            [],
            []
        )];
    }

    public function testFlush(): void
    {
        $profiler = new DefaultProfiler(true);
        $timer1 = $profiler->timer('one');
        $timer2 = $profiler->timer('two');

        self::assertTrue($timer1->isRunning());
        self::assertTrue($timer2->isRunning());

        $profiler->flush();

        self::assertFalse($timer1->isRunning());
        self::assertFalse($timer2->isRunning());
    }
}
