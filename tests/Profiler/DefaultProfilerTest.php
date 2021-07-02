<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Profiler;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerClosedError;
use MakinaCorpus\Profiling\Bridge\Symfony5\Stopwatch\StopwatchProfilerDecorator;
use MakinaCorpus\Profiling\Implementation\DefaultProfiler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Stopwatch\Stopwatch;

final class DefaultProfilerTest extends TestCase
{
    public static function getProfilers()
    {
        yield [new DefaultProfiler()];
        yield [new StopwatchProfilerDecorator(new Stopwatch(), new DefaultProfiler())];
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

    /**
     * @dataProvider getProfilers
     */
    public function testStart(Profiler $profiler): void
    {
        $child = $profiler->start();

        self::assertTrue($profiler->isRunning());
        self::assertTrue($child->isRunning());

        self::assertContains($child, $profiler->getChildren());
    }

    /**
     * @dataProvider getProfilers
     */
    public function testStartRaiseErrorIfStopped(Profiler $profiler): void
    {
        $profiler->stop();

        self::expectException(ProfilerClosedError::class);
        $profiler->start();
    }

    /**
     * @dataProvider getProfilers
     */
    public function testStop(Profiler $profiler): void
    {
        $child = $profiler->start();

        self::assertTrue($profiler->isRunning());
        self::assertTrue($child->isRunning());

        $profiler->stop();
        self::assertFalse($profiler->isRunning());
        self::assertFalse($child->isRunning());
    }

    /**
     * @dataProvider getProfilers
     */
    public function testStopChild(Profiler $profiler): void
    {
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

    /**
     * @dataProvider getProfilers
     */
    public function testStopChildNonExisting(Profiler $profiler): void
    {
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

    /**
     * @dataProvider getProfilers
     */
    public function testIsRunning(Profiler $profiler): void
    {
        self::assertTrue($profiler->isRunning());

        $profiler->stop();
        self::assertFalse($profiler->isRunning());
    }

    /**
     * @dataProvider getProfilers
     */
    public function testGetName(Profiler $profiler): void
    {
        $profiler = new DefaultProfiler('foo');
        self::assertSame('foo', $profiler->getName());
    }

    /**
     * @dataProvider getProfilers
     */
    public function testGetAbsoluteName(Profiler $profiler): void
    {
        $child1 = $profiler->start('bar');
        $child2 = $child1->start('baz');

        self::assertSame($profiler->getId() . '/bar/baz', $child2->getAbsoluteName());
    }

    /**
     * @dataProvider getProfilers
     */
    public function testGetNameReturnIdIfNull(Profiler $profiler): void
    {
        self::assertSame($profiler->getId(), $profiler->getName());
    }

    /**
     * @dataProvider getProfilers
     */
    public function testGetRelativeStartTime(Profiler $profiler): void
    {
        self::markTestIncomplete();
    }

    /**
     * @dataProvider getProfilers
     */
    public function testGetRelativeStartTimeReturnZeroIfNoParent(Profiler $profiler): void
    {
        self::assertEquals(0.0, $profiler->getRelativeStartTime());
    }

    /**
     * @dataProvider getProfilers
     */
    public function testGetAbsoluteStartTime(Profiler $profiler): void
    {
        self::markTestIncomplete();
    }

    /**
     * @dataProvider getProfilers
     */
    public function testGetAbsoluteStartTimeReturnZeroIfNoParent(Profiler $profiler): void
    {
        self::assertEquals(0.0, $profiler->getAbsoluteStartTime());
    }

    /**
     * @dataProvider getProfilers
     */
    public function testGetElapsedTimeWhileRunning(Profiler $profiler): void
    {
        $elapsed1 = $profiler->getElapsedTime();
        self::assertNotNull($elapsed1);

        $elapsed2 = $profiler->getElapsedTime();
        self::assertGreaterThan($elapsed1, $elapsed2);
    }

    /**
     * @dataProvider getProfilers
     */
    public function testGetElapsedTimeOnceStopped(Profiler $profiler): void
    {
        $profiler->stop();

        $elapsed1 = $profiler->getElapsedTime();
        self::assertNotNull($elapsed1);

        $elapsed2 = $profiler->getElapsedTime();
        self::assertEquals($elapsed1, $elapsed2);
    }

    /**
     * @dataProvider getProfilers
     */
    public function testGetSetAttributes(Profiler $profiler): void
    {
        $profiler->setAttribute('foo', '12');
        $profiler->stop();
        $profiler->setAttribute('bar', 12.7);

        self::assertSame(['foo' => '12', 'bar' => 12.7], $profiler->getAttributes());
    }
}
