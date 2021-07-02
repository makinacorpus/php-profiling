<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Profiler;

use MakinaCorpus\Profiling\ProfilerClosedError;
use MakinaCorpus\Profiling\Implementation\DefaultProfiler;
use PHPUnit\Framework\TestCase;

final class DefaultProfilerTest extends TestCase
{
    public function testStart(): void
    {
        $profiler = new DefaultProfiler();
        $child = $profiler->start();

        self::assertTrue($profiler->isRunning());
        self::assertTrue($child->isRunning());

        self::assertContains($child, $profiler->getChildren());
    }

    public function testStartRaiseErrorIfStopped(): void
    {
        $profiler = new DefaultProfiler();
        $profiler->stop();

        self::expectException(ProfilerClosedError::class);
        $profiler->start();
    }

    public function testStop(): void
    {
        $profiler = new DefaultProfiler();
        $child = $profiler->start();

        self::assertTrue($profiler->isRunning());
        self::assertTrue($child->isRunning());

        $profiler->stop();
        self::assertFalse($profiler->isRunning());
        self::assertFalse($child->isRunning());
    }

    public function testStopChild(): void
    {
        $profiler = new DefaultProfiler();
        $child1 = $profiler->start();
        $child2 = $profiler->start('foo');

        self::assertTrue($profiler->isRunning());
        self::assertTrue($child1->isRunning());
        self::assertTrue($child2->isRunning());

        $profiler->stop('foo');
        self::assertTrue($profiler->isRunning());
        self::assertTrue($child1->isRunning());
        self::assertFalse($child2->isRunning());
    }

    public function testStopChildNonExisting(): void
    {
        $profiler = new DefaultProfiler();
        $child1 = $profiler->start();
        $child2 = $profiler->start('foo');

        self::assertTrue($profiler->isRunning());
        self::assertTrue($child1->isRunning());
        self::assertTrue($child2->isRunning());

        $profiler->stop('bar');
        self::assertTrue($profiler->isRunning());
        self::assertTrue($child1->isRunning());
        self::assertTrue($child2->isRunning());
    }

    public function testIsRunning(): void
    {
        $profiler = new DefaultProfiler();
        self::assertTrue($profiler->isRunning());

        $profiler->stop();
        self::assertFalse($profiler->isRunning());
    }

    public function testGetName(): void
    {
        $profiler = new DefaultProfiler('foo');
        self::assertSame('foo', $profiler->getName());
    }

    public function testGetAbsoluteName(): void
    {
        $profiler = new DefaultProfiler('foo');
        $child1 = $profiler->start('bar');
        $child2 = $child1->start('baz');

        self::assertSame('foo/bar/baz', $child2->getAbsoluteName());
    }

    public function testGetNameReturnIdIfNull(): void
    {
        $profiler = new DefaultProfiler();
        self::assertSame($profiler->getId(), $profiler->getName());
    }

    public function testGetId(): void
    {
        $profiler1 = new DefaultProfiler();
        $profiler2 = new DefaultProfiler();
        $profiler3 = new DefaultProfiler();

        self::assertNotEquals($profiler1->getId(), $profiler2->getName());
        self::assertNotEquals($profiler1->getId(), $profiler3->getName());
        self::assertNotEquals($profiler2->getId(), $profiler3->getName());
    }

    public function testGetRelativeStartTime(): void
    {
        self::markTestIncomplete();
    }

    public function testGetRelativeStartTimeReturnZeroIfNoParent(): void
    {
        $profiler = new DefaultProfiler();
        self::assertEquals(0.0, $profiler->getRelativeStartTime());
    }

    public function testGetAbsoluteStartTime(): void
    {
        self::markTestIncomplete();
    }

    public function testGetAbsoluteStartTimeReturnZeroIfNoParent(): void
    {
        $profiler = new DefaultProfiler();
        self::assertEquals(0.0, $profiler->getAbsoluteStartTime());
    }

    public function testGetElapsedTimeWhileRunning(): void
    {
        $profiler = new DefaultProfiler();

        $elapsed1 = $profiler->getElapsedTime();
        self::assertNotNull($elapsed1);

        $elapsed2 = $profiler->getElapsedTime();
        self::assertGreaterThan($elapsed1, $elapsed2);
    }

    public function testGetElapsedTimeOnceStopped(): void
    {
        $profiler = new DefaultProfiler();
        $profiler->stop();

        $elapsed1 = $profiler->getElapsedTime();
        self::assertNotNull($elapsed1);

        $elapsed2 = $profiler->getElapsedTime();
        self::assertEquals($elapsed1, $elapsed2);
    }
}
