<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Implementation;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\Implementation\DefaultProfiler;
use MakinaCorpus\Profiling\Implementation\NullProfiler;
use PHPUnit\Framework\TestCase;

final class DefaultProfilerTest extends TestCase
{
    public static function getProfilers()
    {
        yield [new DefaultProfiler()];
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
    public function testGetDescription(Profiler $profiler): void
    {
        self::assertNull($profiler->getDescription());

        $profiler->setDescription('Test');
        self::assertSame('Test', $profiler->getDescription());
    }

    /**
     * @dataProvider getProfilers
     */
    public function testGetMemoryGetUsageStart(Profiler $profiler): void
    {
        self::assertGreaterThan(0, $profiler->execute()->getMemoryUsageStart());
    }

    /**
     * @dataProvider getProfilers
     */
    public function testGetMemoryGetUsage(Profiler $profiler): void
    {
        $profiler->execute();
        $foo = new \DateTimeImmutable();
        self::assertGreaterThan(0, $profiler->getMemoryUsageStart());

        $usage1 = $profiler->getMemoryUsage();

        $a = \random_bytes(32);
        $usage2 = $profiler->getMemoryUsage();

        $profiler->stop();
        $usage3 = $profiler->getMemoryUsage();

        self::assertNotNull($a); // Just to avoid garbage collection of $a
        self::assertGreaterThan(0, $usage1);
        self::assertGreaterThan($usage1, $usage2);

        if (DefaultProfiler::class === \get_class($profiler)) {
            // With decorators, this might be untrue since they mess up
            // with other components.
            self::assertSame($usage2, $usage3);
        }
    }

    /**
     * @dataProvider getProfilers
     */
    public function testStart(Profiler $profiler): void
    {
        $child = $profiler->execute()->start();

        self::assertTrue($profiler->isRunning());
        self::assertTrue($child->isRunning());

        self::assertContains($child, $profiler->getChildren());
    }

    /**
     * @dataProvider getProfilers
     */
    public function testStartReturnNullInstanceIfStopped(Profiler $profiler): void
    {
        $profiler->execute();
        $profiler->stop();

        $other = $profiler->start();
        self::assertInstanceOf(NullProfiler::class, $other);
    }

    /**
     * @dataProvider getProfilers
     */
    public function testStop(Profiler $profiler): void
    {
        $child = $profiler->execute()->start();

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
        $child1 = $profiler->execute()->start();
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
        $child1 = $profiler->execute()->start();
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
        self::assertFalse($profiler->isRunning());
        $profiler->execute();
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
        $profiler->execute();
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
