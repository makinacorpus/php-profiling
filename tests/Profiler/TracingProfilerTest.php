<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Profiler;

use MakinaCorpus\Profiling\Profiler\DefaultProfiler;
use MakinaCorpus\Profiling\Profiler\DispatchProfilerDecorator;
use MakinaCorpus\Profiling\Profiler\TracingProfilerDecorator;
use PHPUnit\Framework\TestCase;

final class TracingProfilerTest extends TestCase
{
    public function testNoChannelGoesToDefault(): void
    {
        $handlerDefault = new TestingTraceHandler();
        $handlerOther = new TestingTraceHandler();

        $profiler = new TracingProfilerDecorator(
            new DefaultProfiler(true),
            [
                'default' => $handlerDefault,
                'other' => $handlerOther,
            ],
            [
                'default' => ['!foo', '!bar'],
                'other' => ['foo', 'bar'],
            ]
        );

        $timerFizz = $profiler->timer('fizz');
        $timerBuzz = $profiler->timer('buzz');

        self::assertSame([], $timerFizz->getChannels());
        self::assertSame([], $timerBuzz->getChannels());

        $timerFizz->stop();

        self::assertSame(['fizz', 'buzz'], $handlerDefault->getAll());
        self::assertSame(['fizz'], $handlerDefault->getStopped());

        $timerBuzz->stop();

        self::assertSame(['fizz', 'buzz'], $handlerDefault->getAll());
        self::assertSame(['fizz', 'buzz'], $handlerDefault->getStopped());

        self::assertSame([], $handlerOther->getAll());
        self::assertSame([], $handlerOther->getAllNoFlush());
    }

    public function testUnsupportedChannelGoesToDefault(): void
    {
        $handlerDefault = new TestingTraceHandler();
        $handlerOther = new TestingTraceHandler();

        $profiler = new TracingProfilerDecorator(
            new DefaultProfiler(true),
            [
                'default' => $handlerDefault,
                'other' => $handlerOther,
            ],
            [
                'default' => ['!foo', '!bar'],
                'other' => ['foo', 'bar'],
            ]
        );

        $timer = (new DispatchProfilerDecorator($profiler, ['bla']))->timer('--default');

        self::assertSame(['bla'], $timer->getChannels());
        self::assertSame(['--default'], $handlerDefault->getAll());
        $timer->stop();
        self::assertSame(['--default'], $handlerDefault->getAll());
        self::assertSame(['--default'], $handlerDefault->getStopped());

        self::assertSame([], $handlerOther->getAll());
        self::assertSame([], $handlerOther->getAllNoFlush());
    }

    public function testChildProfilerGoesToRightChannel(): void
    {
        $handlerDefault = new TestingTraceHandler();
        $handlerOther = new TestingTraceHandler();

        $profiler = new TracingProfilerDecorator(
            new DefaultProfiler(true),
            [
                'default' => $handlerDefault,
                'other' => $handlerOther,
            ],
            [
                'other' => ['bar'],
            ]
        );

        $timerDefault = (new DispatchProfilerDecorator($profiler, ['foo']))->timer('--default');
        $timerDefaultChild = $timerDefault->start('child');

        $timerOther = (new DispatchProfilerDecorator($profiler, ['bar']))->timer('--other');
        $timerOtherChild = $timerOther->start('child');

        self::assertSame(['--default', '--default/child', '--other', '--other/child'], $handlerDefault->getAll());
        self::assertSame(['--other', '--other/child'], $handlerOther->getAll());

        $timerDefault->stop();
        $timerDefaultChild->stop();
        $timerOther->stop();
        $timerOtherChild->stop();

        self::assertSame(['--default', '--default/child', '--other', '--other/child'], $handlerDefault->getStopped());
        self::assertSame(['--other', '--other/child'], $handlerOther->getStopped());
    }

    public function testWhiteList(): void
    {
        $handlerDefault = new TestingTraceHandler();
        $handlerOther = new TestingTraceHandler();

        $profiler = new TracingProfilerDecorator(
            new DefaultProfiler(true),
            [
                'default' => $handlerDefault,
                'other' => $handlerOther,
            ],
            [
                'default' => ['!bar'],
                'other' => ['bar'],
            ]
        );

        $timerDefault = (new DispatchProfilerDecorator($profiler, ['foo']))->timer('--default');
        $timerBoth = (new DispatchProfilerDecorator($profiler, ['foo', 'bar']))->timer('--both');
        $timerOther = (new DispatchProfilerDecorator($profiler, ['bar']))->timer('--other');

        self::assertSame(['--default', '--both'], $handlerDefault->getAll());
        self::assertSame(['--both', '--other'], $handlerOther->getAll());

        $timerDefault->stop();
        $timerBoth->stop();
        $timerOther->stop();

        self::assertSame(['--default', '--both'], $handlerDefault->getStopped());
        self::assertSame(['--both', '--other'], $handlerOther->getStopped());
    }

    public function testBlackList(): void
    {
        $handlerDefault = new TestingTraceHandler();
        $handlerOther = new TestingTraceHandler();

        $profiler = new TracingProfilerDecorator(
            new DefaultProfiler(true),
            [
                'default' => $handlerDefault,
                'other' => $handlerOther,
            ],
            [
                'default' => ['!bar'],
                'other' => ['!foo'], // Catch all except foo.
            ]
        );

        $timerDefault = (new DispatchProfilerDecorator($profiler, ['foo']))->timer('--default');
        $timerBoth = (new DispatchProfilerDecorator($profiler, ['foo', 'bar']))->timer('--both');
        $timerOther = (new DispatchProfilerDecorator($profiler, ['bar']))->timer('--other');

        self::assertSame(['--default', '--both'], $handlerDefault->getAll());
        self::assertSame(['--both', '--other'], $handlerOther->getAll());

        $timerDefault->stop();
        $timerBoth->stop();
        $timerOther->stop();

        self::assertSame(['--default', '--both'], $handlerDefault->getStopped());
        self::assertSame(['--both', '--other'], $handlerOther->getStopped());
    }
}
