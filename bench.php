<?php

use MakinaCorpus\Profiling\Profiler\DefaultProfiler;
use MakinaCorpus\Profiling\Profiler\DispatchProfilerDecorator;
use MakinaCorpus\Profiling\Profiler\TracingProfilerDecorator;
use MakinaCorpus\Profiling\Prometheus\Logger\MemorySampleLogger;
use MakinaCorpus\Profiling\Prometheus\Schema\ArraySchema;
use MakinaCorpus\Profiling\Prometheus\Storage\NullStorage;
use MakinaCorpus\Profiling\RequestContext;
use MakinaCorpus\Profiling\Tests\Profiler\TestingTraceHandler;
use MakinaCorpus\Profiling\Timer\Handler\Formatter\JsonFormatter;
use MakinaCorpus\Profiling\Timer\Handler\StreamHandler;
use MakinaCorpus\Profiling\Timer\Handler\SymfonyStopwatchHandler;
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

$context = new DispatchProfilerDecorator(
    new TracingProfilerDecorator(
        new DefaultProfiler(
            true,
            true,
            new MemorySampleLogger(
                new ArraySchema(
                    'symfony',
                    [], // @todo schema
                ),
                new NullStorage(),
            ),
        ),
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

$context->enterContext(RequestContext::null());

$start = microtime(true);
$max = 1000;
for ($i = 0; $i < $max; ++$i) {
    $timer = $context->createTimer('foo');
    $child1 = $timer->start('fizz');
    $child2 = $timer->start('bla');

    $child2->stop();
    $timer->stop();
}
echo "Time [timer,child,child] (", $max, " iterations): ", (microtime(true) - $start) * 1000, " ms\n";

$start = microtime(true);
for ($i = 0; $i < $max; ++$i) {
    $context->counter('foo', []);
    $context->summary('bar', [], 10.6);
    $context->gauge('fizz', [], 37.6);
}
echo "Time [counter,summary,gauge] (", $max, " iterations): ", (microtime(true) - $start) * 1000, " ms\n";

