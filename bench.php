<?php

use MakinaCorpus\Profiling\Handler\StreamHandler;
use MakinaCorpus\Profiling\Handler\SymfonyStopwatchHandler;
use MakinaCorpus\Profiling\Handler\Formatter\JsonFormatter;
use MakinaCorpus\Profiling\ProfilerContext\DispatchProfilerContextDecorator;
use MakinaCorpus\Profiling\ProfilerContext\MemoryProfilerContext;
use MakinaCorpus\Profiling\ProfilerContext\TracingProfilerContextDecorator;
use MakinaCorpus\Profiling\Tests\ProfilerContext\TestingTraceHandler;
use Symfony\Component\Stopwatch\Stopwatch;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * I have to admit, this bench is stupid.
 *
 * It's only purpose is to be able to measure if this API will have a noticeable
 * impact on production runtime or not.
 */

$plainTextFormatter = new StreamHandler(sys_get_temp_dir() . '/profiling.txt');
$jsonTextFormatter = new StreamHandler(sys_get_temp_dir() . '/profiling.json');
$jsonTextFormatter->setFormatter(new JsonFormatter());

$context = new DispatchProfilerContextDecorator(
    new TracingProfilerContextDecorator(
        new MemoryProfilerContext(),
        [
            'default' => new TestingTraceHandler(),
            'other' => new SymfonyStopwatchHandler(
                new Stopwatch()
            ),
            'file' => $plainTextFormatter,
            'json' => $jsonTextFormatter,
        ],
        [
            'default' => ['!foo', '!bar'],
            'other' => ['foo', 'bar'],
        ]
    ),
    ['foo', 'bar', 'baz']
);

$start = microtime(true);
for ($i = 0; $i < 1000; ++$i) {
    $profiler = $context->create('foo');
    $child1 = $profiler->start('fizz');
    $child2 = $profiler->start('bla');

    $child2->stop();
    $profiler->stop();
}
echo "Time: ", (microtime(true) - $start) * 1000, " ms\n";
