<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Tests\Implementation;

use PHPUnit\Framework\TestCase;
use MakinaCorpus\Profiling\Implementation\TracingProfilerContextDecorator;
use MakinaCorpus\Profiling\Implementation\DispatchProfilerContextDecorator;
use MakinaCorpus\Profiling\Implementation\MemoryProfilerContext;

final class TracingProfilerContextTest extends TestCase
{
    public function testNoChannelGoesToDefault(): void
    {
        $handlerDefault = new TestingTraceHandler();
        $handlerOther = new TestingTraceHandler();

        $context = new TracingProfilerContextDecorator(
            new MemoryProfilerContext(),
            [
                'default' => $handlerDefault,
                'other' => $handlerOther,
            ],
            [
                'default' => ['!foo', '!bar'],
                'other' => ['foo', 'bar'],
            ]
        );

        $profilerFizz = $context->start('fizz');
        $profilerBuzz = $context->start('buzz');

        self::assertSame([], $profilerFizz->getChannels());
        self::assertSame([], $profilerBuzz->getChannels());

        $profilerFizz->stop();

        self::assertSame(['fizz', 'buzz'], $handlerDefault->getAll());
        self::assertSame(['fizz'], $handlerDefault->getStopped());

        $profilerBuzz->stop();

        self::assertSame(['fizz', 'buzz'], $handlerDefault->getAll());
        self::assertSame(['fizz', 'buzz'], $handlerDefault->getStopped());

        self::assertSame([], $handlerOther->getAll());
        self::assertSame([], $handlerOther->getAllNoFlush());
    }

    public function testUnsupportedChannelGoesToDefault(): void
    {
        $handlerDefault = new TestingTraceHandler();
        $handlerOther = new TestingTraceHandler();

        $context = new TracingProfilerContextDecorator(
            new MemoryProfilerContext(),
            [
                'default' => $handlerDefault,
                'other' => $handlerOther,
            ],
            [
                'default' => ['!foo', '!bar'],
                'other' => ['foo', 'bar'],
            ]
        );

        $profiler = (new DispatchProfilerContextDecorator($context, ['bla']))->start('--default');

        self::assertSame(['bla'], $profiler->getChannels());
        self::assertSame(['--default'], $handlerDefault->getAll());
        $profiler->stop();
        self::assertSame(['--default'], $handlerDefault->getAll());
        self::assertSame(['--default'], $handlerDefault->getStopped());

        self::assertSame([], $handlerOther->getAll());
        self::assertSame([], $handlerOther->getAllNoFlush());
    }

    public function testChildProfilerGoesToRightChannel(): void
    {
        $handlerDefault = new TestingTraceHandler();
        $handlerOther = new TestingTraceHandler();

        $context = new TracingProfilerContextDecorator(
            new MemoryProfilerContext(),
            [
                'default' => $handlerDefault,
                'other' => $handlerOther,
            ],
            [
                'other' => ['bar'],
            ]
        );

        $profilerDefault = (new DispatchProfilerContextDecorator($context, ['foo']))->start('--default');
        $profilerDefaultChild = $profilerDefault->start('child');

        $profilerOther = (new DispatchProfilerContextDecorator($context, ['bar']))->start('--other');
        $profilerOtherChild = $profilerOther->start('child');

        self::assertSame(['--default', '--default/child', '--other', '--other/child'], $handlerDefault->getAll());
        self::assertSame(['--other', '--other/child'], $handlerOther->getAll());

        $profilerDefault->stop();
        $profilerDefaultChild->stop();
        $profilerOther->stop();
        $profilerOtherChild->stop();

        self::assertSame(['--default', '--default/child', '--other', '--other/child'], $handlerDefault->getStopped());
        self::assertSame(['--other', '--other/child'], $handlerOther->getStopped());
    }

    public function testWhiteList(): void
    {
        $handlerDefault = new TestingTraceHandler();
        $handlerOther = new TestingTraceHandler();

        $context = new TracingProfilerContextDecorator(
            new MemoryProfilerContext(),
            [
                'default' => $handlerDefault,
                'other' => $handlerOther,
            ],
            [
                'default' => ['!bar'],
                'other' => ['bar'],
            ]
        );

        $profilerDefault = (new DispatchProfilerContextDecorator($context, ['foo']))->start('--default');
        $profilerBoth = (new DispatchProfilerContextDecorator($context, ['foo', 'bar']))->start('--both');
        $profilerOther = (new DispatchProfilerContextDecorator($context, ['bar']))->start('--other');

        self::assertSame(['--default', '--both'], $handlerDefault->getAll());
        self::assertSame(['--both', '--other'], $handlerOther->getAll());

        $profilerDefault->stop();
        $profilerBoth->stop();
        $profilerOther->stop();

        self::assertSame(['--default', '--both'], $handlerDefault->getStopped());
        self::assertSame(['--both', '--other'], $handlerOther->getStopped());
    }

    public function testBlackList(): void
    {
        $handlerDefault = new TestingTraceHandler();
        $handlerOther = new TestingTraceHandler();

        $context = new TracingProfilerContextDecorator(
            new MemoryProfilerContext(),
            [
                'default' => $handlerDefault,
                'other' => $handlerOther,
            ],
            [
                'default' => ['!bar'],
                'other' => ['!foo'], // Catch all except foo.
            ]
        );

        $profilerDefault = (new DispatchProfilerContextDecorator($context, ['foo']))->start('--default');
        $profilerBoth = (new DispatchProfilerContextDecorator($context, ['foo', 'bar']))->start('--both');
        $profilerOther = (new DispatchProfilerContextDecorator($context, ['bar']))->start('--other');

        self::assertSame(['--default', '--both'], $handlerDefault->getAll());
        self::assertSame(['--both', '--other'], $handlerOther->getAll());

        $profilerDefault->stop();
        $profilerBoth->stop();
        $profilerOther->stop();

        self::assertSame(['--default', '--both'], $handlerDefault->getStopped());
        self::assertSame(['--both', '--other'], $handlerOther->getStopped());
    }
}
