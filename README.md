# Profiling toolbox

Profiling and metrics toolbox.

Contains many features:

 - Timer API, very much alike `symfony/stopwatch` with time and memory
   consumption measurement. Timers can be started within a timer tree
   which allows to have a multi-dimensional view of timers.

 - Prometheus compatible various metrics collection, counters, gauges,
   summaries and histograms. Those metrics can be exposed via a Promotheus
   compatible scrapping endpoint.

 - Integration with `symfony/stopwatch` for Symfony web profiler when in
   development mode.

Timers uses the monotonic high resolution timer if available using the PHP
`\hrtime()` function for timings, which yields more precision and is resilient
to system clock changes in opposition to `\microtime()` Using `\hrtime()`
function makes this API being suitable for running discretly in production.

# Setup

Simply run:

```sh
composer require makinacorpus/profiling
```

For registering the Symfony bundle, add to your `config/bundles.php`:

```php
return [
    // ... Your other bundles.
    MakinaCorpus\Profiling\Bridge\Symfony\ProfilingBundle::class => ['all' => true],
];
```

Then copy the `src/Bridge/Symfony/Resources/packages/profiling.yaml` in this
package in the `config/packages/` directory. You may read it and modify
following your needs. All configuration options are documented within the
sample configuration file itself.

# Usage

Important notes:

 - For each incomming request, the must be one and only one
   `MakinaCorpus\Profiling\Profiler` instance.

 - By "incomming request", we mean a single workload, which in the context of
   a message bus consumer can be a single message processing.

## Timer basic usage

```php
use MakinaCorpus\Profiling\Profiler\DefaultProfiler;

// First, create a profiler. If you are using a framework, you should
// inject in your dependency injection container a global instance.
$profiler = DefaultProfiler();

// Start a new top-level timer, which has no parent.
// Please note that name is optional, it's purely informational.
// A unique identifier will be generated if you don't pass one.
// You need a name later if you wish to stop one timer without
// stopping all the others.
$timer = $profiler->start('doing-something');

// Each time you start a new top-level profiler, it is decoupled from
// the other one, they won't interact with each-ohter.
$otherTimer = $profiler->start('unrelated-other-something');

// From your first timer, you can start children.
$timer1 = $timer->start('1');
$timer2 = $timer->start('2');

// Then subchildren.
$timer21 = $timer2->start('2.1');
$timer22 = $timer2->start('2.2');

// From a parent timer, you can choose stopping only one child.
// You can stop the child directly as well.
// The following two lines are equivalent gives a strictly identical result.
$timer2 = $timer2->stop('2.2');
$timer22->stop();

echo $timer2->isRunning(); // true
echo $timer21->isRunning(); // true
echo $timer22->isRunning(); // false

// When you close a timer, all the children will be stopped as well.
$timer2 = $timer->stop();

echo $timer2->isRunning(); // false
echo $timer21->isRunning(); // false
echo $timer22->isRunning(); // false

// You can fetch timings.
// All given numbers are float, reprensenting a number of milliseconds.
echo $timer2->getElapsedTime(); // 2.2124548
echo $timer21->getElapsedTime(); // 1.88878889
echo $timer22->getElapsedTime(); // 0.98897574

// You can fully reset the global state, which will also free the
// memory it took.
// This is precious for long running deamons, such as message bus
// consumers which will remain alive for hours.
$profiler->flush();
```

## Timer advanced usage

There are many methods on the `\MakinaCorpus\Profiling\Timer` interface, all are documented.

## Prometheus metrics

### Setup

First, enable it in your `config/packages/profiling.yaml` file:

```yaml
profiling:
    prometheus:
        enable: true
```

Then compute a random access token, with any method of your choice, then set
it into your environments variables:

```env
PROMETHEUS_METRICS_ACCESS_TOKEN: SuperSecretToken
```

In order to setup the prometheus HTTP endpoint, add into `config/routes.yaml`:

```yaml
prometheus:
    resource: "@ProfilingBundle/Resources/config/routes.prometheus.yaml"
    prefix: /prometheus
```

Then for fetching metrics, simply hit the following URL:

```sh
curl http://yoursite/prometheus/metrics/?access_token=SuperSecretToken
```

Also, please note that if you configured some firewalls, you probably need
to put the `^/prometheus/` path into a non-secured firewall.

Default configuration simply works, aside for the driver that needs to
be configured. Default is `in_memory` which means it simply stores nothing.

### Define your own metrics

Each sample must be defined in the `schema` configuration section. If a sample
is not defined in this file, then it will simply be a no-op if you attempt
collecting it.

Edit your `config/packages/profiling.yaml` file:

```yaml
profiling:
    prometheus:
        #
        # Namespace name will prefix all sample names when exported to
        # Prometheus. For example, if you define "some_counter", the final
        # sample name will be "symfony_some_counter".
        #
        namespace: symfony

        #
        # For all sample type, you may set the "labels" entry.
        #
        # Labels are some kind tags, whose values are required when
        # measuring. This is important, please read Prometheus documentation
        # for more information.
        #
        # In all cases, you always should add the [action, method] which then
        # should be populated using respectively the current route name and
        # the HTTP method. There are of course some use cases where you may
        # not need it.
        #
        schema:

            # Gauge is a float value, no more no less, each new measure
            # will erase the existing value.
            some_gauge:
                type: gauge
                help: This is some gauge
                labels: [action, method, foo, bar]

            # A counter is a static value that gets incremented over time.
            # It never gets reseted, always incremented.
            # If you drop the data from your database, the next Prometheus
            # scrap will see the value going down and give invalid data,
            # but as time will pass, data will get eventually consistent
            # soon enough.
            some_counter:
                type: counter
                help: Some counter
                labels: [action, method, foo]

            # Summary is statistical distribution analysis of some value using
            # percentiles. Summaries are computed on the client side (ie.
            # your site) in opposition to histograms which are computed on
            # the service (ie. in Prometheus).
            some_summary:
                type: summary
                help: Some summary
                labels: [action, method, foo]

            # Histogram is a statistical distribution analysis of some value
            # using buckets. Buckets are supposed to be pre-defined in this
            # schema. Histograms are computed in the server side (ie. in
            # Prometheus) in opposition to summaries which are computed on
            # the client side (ie. your site).
            some_histogram:
                type: histogram
                help: request durations in milliseconds
                labels: [action, method, foo]
```

Then, at the point in code where you need to profile, inject the
`MakinaCorpus\Profiling\Profiler` service and use it.

#### Gauge

Use the `gauge()` method:

```php
\assert($profiler instanceof \MakinaCorpus\Profiling\Profiler);

$timer = $profiler->gauge(
    // Name in your schema.
    'some_gauge',
    // Label values, considering you kept "action" and "method"
    // in the (action, method, foo, bar) list:
    [
        $profiler->getContext()->route,
        $profiler->getContext()->method,
        'some_value',
        'some_other_value',
    ],
    // Arbitrary value you actually measure.
    123.456
);
```

#### Counter

Use the `counter()` method:

```php
\assert($profiler instanceof \MakinaCorpus\Profiling\Profiler);

$timer = $profiler->counter(
    // Name in your schema.
    'some_counter',
    // Label values, considering you kept "action" and "method"
    // in the (action, method, foo) list:
    [
        $profiler->getContext()->route,
        $profiler->getContext()->method,
        'some_value',
    ],
    // Arbitrary increment value you actually measure.
    // You can omit this parameter and increment will be 1.
    3,
);
```

#### Summary

Use the `summary` method in conjonction with a timer:

```php
\assert($profiler instanceof \MakinaCorpus\Profiling\Profiler);

try {
    $timer = $profiler->timer();

    // Something happens, then...
} finally {
    if ($timer) {
        $duration = $timer->getElapsedTime();

        $profiler->summary(
            'something_duration_msec',
            // Label values, considering you kept "action" and "method"
            // in the (action, method, foo) list:
            [
                $profiler->getContext()->route,
                $profiler->getContext()->method,
                'some_value',
            ],
            // Arbitrary value you actually measure.
            $duration,
        );
    }
}
```

Of course, you could measure something else than a duration, any value
which yield some meanings to you can be added to summaries.

#### Histogram

Histograms are not implemented yet.

### Exposed metrics

#### HTTP requests

 - `NAMESPACE_http_exception_total` (`counter`), `{action: ROUTE, method: HTTP_METHOD, type: EXCEPTION_CLASS}`
 - `NAMESPACE_http_request_duration_msec` (`summary`), `{action: ROUTE, method: HTTP_METHOD, status: HTTP_STATUS_CODE}`
 - `NAMESPACE_http_request_total` (`counter`), `{action: ROUTE, method: HTTP_METHOD}`
 - `NAMESPACE_http_response_total` (`counter`), `{action: ROUTE, method: HTTP_METHOD, status: HTTP_STATUS_CODE}`
 - `NAMESPACE_instance_name` (`gauge`), `{instance_name: HOSTNAME}`

#### Console commands

 - `NAMESPACE_console_command_total` (`counter`), `{action: COMMAND_NAME}`
 - `NAMESPACE_console_duration_msec` (`summary`), `{action: COMMAND_NAME, method: "command", status: EXIT_STATUS_CODE}`
 - `NAMESPACE_console_exception_total` (`counter`), `{action: COMMAND_NAME, method: "command", type: EXCEPTION_CLASS}`
 - `NAMESPACE_console_status_total` (`counter`), `{action: COMMAND_NAME, method: "command", status: EXIT_STATUS_CODE}`

#### Messenger

**Not implemented yet.** It will probably be:

 - `NAMESPACE_message_consumed_total` (`counter`), `{action: MESSAGE_CLASS, method: "message"}`
 - `NAMESPACE_message_duration_msec` (`summary`), `{action: MESSAGE_CLASS, method: "message"}`
 - `NAMESPACE_message_exception_total` (`counter`), `{action: MESSAGE_CLASS, method: "message", type: EXCEPTION_CLASS}`

#### Monolog

**Not implemented yet.** It will probably be:

 - `NAMESPACE_monolog_message_total` (`counter`), `{action: ROUTE|COMMAND_NAME|MESSAGE_CLASS, method: "message"|"command"|HTTP_METHOD, severity: MONOLOG_LEVEL, channel: MONOLOG_CHANNEL}`


## Inject profiler into your services

When working in a Symfony project, the recommended way for registering the
profiler onto a service is the following:

```php
namespace MyVendor\MyApp\SomeNamespace;

use MakinaCorpus\Profiling\Implementation\ProfilerAware;
use MakinaCorpus\Profiling\Implementation\ProfilerAwareTrait;

/**
 * Implementing the interface allow autoconfiguration.
 */
class SomeService implements ProfilerAware
{
    use ProfilerAwareTrait;
}
```

By using the `\MakinaCorpus\Profiling\Implementation\ProfilerAwareTrait`
you allow your code to be resilient in case of misinitialisation:

 - If the autoconfiguration failed, it will create a default null instance doing
   nothing, which will have a near-to-zero performance impact.

 - If the bundle is deactivated, it will create a default null instance doing
   nothing, which will have a near-to-zero performance impact.

You can then use the profiler:

```php
namespace MyVendor\MyApp\SomeNamespace;

use MakinaCorpus\Profiling\Implementation\ProfilerAware;
use MakinaCorpus\Profiling\Implementation\ProfilerAwareTrait;

/**
 * Implementing the interface allows autoconfiguration.
 */
class SomeService implements ProfilerAware
{
    /**
     * Using the trait provides a default working implementation.
     */
    use ProfilerAwareTrait;

    public function doSomething()
    {
        $timer = $this->getProfiler()->start('something');

        try {
            $timer->start('something-else');
            $this->doSomethingElse();
            $timer->stop('something-else');

            $timer->start('something-other');
            $this->doSomethingElse();
            $timer->stop('something-other');

            $timer->start('something-that-fails');
            throw new \Exception("Oups, something bad happened.");
            $timer->stop('something-that-fails');

        } finally {
            // We do heavily recommend that use the try/finally
            // pattern to ensure that exceptions will not betry
            // your timers.
            // The last stop() call within the try block will never
            // be called, by stopping the parent timer here, it
            // stops the child as well.
            $timer->stop();
        }
    }
}
```

And that's pretty much it.

## Memory usage

The timer class also measure memory usage, but beware that those results
will be biased by this API itself consuming memory.

## CLI killswitch

If you are working in CLI, and with to disable profiling for long running tasks
or migration batches, simply add the `PROFILING_ENABLE=0` environment variable
in your command line.

This will not completely disable the bundle, this is a soft-disable and will only
prevent profiliers from being created during this runtime, for example:

```sh
PROFILING_ENABLE=0 bin/console app:some-very-long-batch
```

# Roadmap

 - Allow configuration of formatter in stream handler.
 - Implement Redis storage for prometheus metrics.
 - Merge Metrics and Profiler interfaces/classes.
 - Plug timers in prometheus summary and histogram.
 - Prometheus doctrine SQL query count metrics.
 - Prometheus messenger metrics.
 - Prometheus monolog metrics.
 - Prometheus response size metrics.
 - Prometheus unexpected metrics log into monolog.
 - Purge console command with date.
 - View console command.
 - Write documentation site.
