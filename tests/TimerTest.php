<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Timer;

use MakinaCorpus\Profiling\Timer;
use PHPUnit\Framework\TestCase;

final class TimerTest extends TestCase
{
    public static function getTimers()
    {
        yield [new Timer()];
    }

    public function testGetId(): void
    {
        $timer1 = new Timer();
        $timer2 = new Timer();
        $timer3 = new Timer();

        self::assertNotEquals($timer1->getId(), $timer2->getName());
        self::assertNotEquals($timer1->getId(), $timer3->getName());
        self::assertNotEquals($timer2->getId(), $timer3->getName());
    }

    /**
     * @dataProvider getTimers
     */
    public function testGetDescription(Timer $timer): void
    {
        self::assertNull($timer->getDescription());

        $timer->setDescription('Test');
        self::assertSame('Test', $timer->getDescription());
    }

    /**
     * @dataProvider getTimers
     */
    public function testGetMemoryGetUsageStart(Timer $timer): void
    {
        self::assertGreaterThan(0, $timer->execute()->getMemoryUsageStart());
    }

    /**
     * @dataProvider getTimers
     */
    public function testGetMemoryGetUsage(Timer $timer): void
    {
        $timer->execute();
        $foo = new \DateTimeImmutable();
        self::assertGreaterThan(0, $timer->getMemoryUsageStart());

        $usage1 = $timer->getMemoryUsage();

        $a = \random_bytes(32);
        $usage2 = $timer->getMemoryUsage();

        $timer->stop();
        $usage3 = $timer->getMemoryUsage();

        self::assertNotNull($a); // Just to avoid garbage collection of $a
        self::assertGreaterThan(0, $usage1);
        self::assertGreaterThan($usage1, $usage2);

        if (Timer::class === \get_class($timer)) {
            // With decorators, this might be untrue since they mess up
            // with other components.
            self::assertSame($usage2, $usage3);
        }
    }

    /**
     * @dataProvider getTimers
     */
    public function testStart(Timer $timer): void
    {
        $child = $timer->execute()->start();

        self::assertTrue($timer->isRunning());
        self::assertTrue($child->isRunning());

        self::assertContains($child, $timer->getChildren());
    }

    /**
     * @dataProvider getTimers
     */
    public function testStop(Timer $timer): void
    {
        $child = $timer->execute()->start();

        self::assertTrue($timer->isRunning());
        self::assertTrue($child->isRunning());

        $timer->stop();
        self::assertFalse($timer->isRunning());
        self::assertFalse($child->isRunning());
    }

    /**
     * @dataProvider getTimers
     */
    public function testStopChild(Timer $timer): void
    {
        $child1 = $timer->execute()->start();
        $child2 = $timer->start('foo');

        self::assertTrue($timer->isRunning());
        self::assertTrue($child1->isRunning());
        self::assertTrue($child2->isRunning());

        $timer->stop('foo');
        self::assertTrue($timer->isRunning());
        self::assertTrue($child1->isRunning());
        self::assertFalse($child2->isRunning());
    }

    /**
     * @dataProvider getTimers
     */
    public function testStopChildNonExisting(Timer $timer): void
    {
        $child1 = $timer->execute()->start();
        $child2 = $timer->start('foo');

        self::assertTrue($timer->isRunning());
        self::assertTrue($child1->isRunning());
        self::assertTrue($child2->isRunning());

        $timer->stop('bar');
        self::assertTrue($timer->isRunning());
        self::assertTrue($child1->isRunning());
        self::assertTrue($child2->isRunning());
    }

    /**
     * @dataProvider getTimers
     */
    public function testIsRunning(Timer $timer): void
    {
        self::assertFalse($timer->isRunning());
        $timer->execute();
        self::assertTrue($timer->isRunning());

        $timer->stop();
        self::assertFalse($timer->isRunning());
    }

    /**
     * @dataProvider getTimers
     */
    public function testGetName(Timer $timer): void
    {
        $timer = new Timer('foo');
        self::assertSame('foo', $timer->getName());
    }

    /**
     * @dataProvider getTimers
     */
    public function testGetAbsoluteName(Timer $timer): void
    {
        $child1 = $timer->start('bar');
        $child2 = $child1->start('baz');

        self::assertSame($timer->getId() . '/bar/baz', $child2->getAbsoluteName());
    }

    /**
     * @dataProvider getTimers
     */
    public function testGetNameReturnIdIfNull(Timer $timer): void
    {
        self::assertSame($timer->getId(), $timer->getName());
    }

    /**
     * @dataProvider getTimers
     */
    public function testGetRelativeStartTime(Timer $timer): void
    {
        self::markTestIncomplete();
    }

    /**
     * @dataProvider getTimers
     */
    public function testGetRelativeStartTimeReturnZeroIfNoParent(Timer $timer): void
    {
        self::assertEquals(0.0, $timer->getRelativeStartTime());
    }

    /**
     * @dataProvider getTimers
     */
    public function testGetAbsoluteStartTime(Timer $timer): void
    {
        self::markTestIncomplete();
    }

    /**
     * @dataProvider getTimers
     */
    public function testGetAbsoluteStartTimeReturnZeroIfNoParent(Timer $timer): void
    {
        self::assertEquals(0.0, $timer->getAbsoluteStartTime());
    }

    /**
     * @dataProvider getTimers
     */
    public function testGetElapsedTimeWhileRunning(Timer $timer): void
    {
        $timer->execute();
        $elapsed1 = $timer->getElapsedTime();
        self::assertNotNull($elapsed1);

        $elapsed2 = $timer->getElapsedTime();
        self::assertGreaterThan($elapsed1, $elapsed2);
    }

    /**
     * @dataProvider getTimers
     */
    public function testGetElapsedTimeOnceStopped(Timer $timer): void
    {
        $timer->stop();

        $elapsed1 = $timer->getElapsedTime();
        self::assertNotNull($elapsed1);

        $elapsed2 = $timer->getElapsedTime();
        self::assertEquals($elapsed1, $elapsed2);
    }

    /**
     * @dataProvider getTimers
     */
    public function testGetSetAttributes(Timer $timer): void
    {
        $timer->setAttribute('foo', '12');
        $timer->stop();
        $timer->setAttribute('bar', 12.7);

        self::assertSame(['foo' => '12', 'bar' => 12.7], $timer->getAttributes());
    }
}
