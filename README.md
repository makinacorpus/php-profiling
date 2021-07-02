# Profiling toolbox

This library is very much alike the `symfony/stopwatch` component.

This profiler uses the machine high resolution timer if available using the PHP
`\hrtime()` function for timings, which is much much faster than using the
`\microtime()` which may end up doing syscalls. Using `\hrtime()` function
makes this API being suitable for running discretly in production.

# Usage

Important notes:

 - for each incomming request, the must be one and only one
   `MakinaCorpus\Profiling\ProfilingContext` instance,

 - by "incomming request", we mean a single workload, which in the context of
   a message bus consumer can be a single message processing.

## Basic usage

```php
use MakinaCorpus\Profiling\Implementation\DefaultProfilerContext;

// First, create a context. If you are using a framework, you should
// inject in your dependency injection container a global instance.
$context = DefaultProfilerContext();

// Start a new top-level profiler, which has no parent.
// Please note that name is optional, it's purely informational.
// A unique identifier will be generated if you don't pass one.
// You need a name later if you wish to stop one profiler without
// stopping all the others.
$profiler = $context->start('doing-something');

// Each time you start a new top-level profiler, it is decoupled from
// the other one, they won't interact with each-ohter.
$otherProfiler = $context->start('unrelated-other-something');

// From your first profiler, you can start children.
$profiler1 = $profiler->start('1');
$profiler2 = $profiler->start('2');

// Then subchildren.
$profiler21 = $profiler2->start('2.1');
$profiler22 = $profiler2->start('2.2');

// From a parent profiler, you can choose stopping only one child.
// You can stop the child directly as well.
// The following two lines are equivalent gives a strictly identical result.
$profiler2 = $profiler2->stop('2.2');
$profiler22->stop();

echo $profiler2->isRunning(); // true
echo $profiler21->isRunning(); // true
echo $profiler22->isRunning(); // false

// When you close a profiler, all the children will be stopped as well.
$profiler2 = $profiler->stop();

echo $profiler2->isRunning(); // false
echo $profiler21->isRunning(); // false
echo $profiler22->isRunning(); // false

// You can fetch timings.
// All given numbers are float, reprensenting a number of milliseconds.
echo $profiler2->getElapsedTime(); // 2.2124548
echo $profiler21->getElapsedTime(); // 1.88878889
echo $profiler22->getElapsedTime(); // 0.98897574

// You can fully reset the global state, which will also free the
// memory it took.
// This is precious for long running deamons, such as message bus
// consumers which will remain alive for hours.
$context->flush();
```

## Advanced usage

There are many methods on the `\MakinaCorpus\Profiling\Profiler` interface, all are documented.

# Symfony

## Setup the Symfony bundle

This component provides a Symfony 5+ bundle, all you need is to register it
into your `config/bundles.php` file:

```php
return [
    // ... Your other bundles.
    MakinaCorpus\Profiling\Bridge\Symfony5\ProfilingBundle::class => ['all' => true],
];
```

Please note that this profiling API is very fast, its overhead will not be
noticeable so it is suitable for a production environment. You can safely
enable it on all environements.

No configuration is required, a dedicated instance of `MakinaCorpus\Profiling\ProfilerContext`
will be registered into the container, you can inject it into your object.

## Setup your services for using the profiler

Nevertheless, the recommended way for registering the context onto a service
is the following:

```php
namespace MyVendor\MyApp\SomeNamespace;

use MakinaCorpus\Profiling\Implementation\ProfilerContextAware;
use MakinaCorpus\Profiling\Implementation\ProfilerContextAwareTrait;

/**
 * Implementing the interface allow autoconfiguration.
 */
class SomeService implements ProfilerContextAware
{
    /**
     * Using the trait provides a default working implementation.
     */
    use ProfilerContextAwareTrait;
}
```

By using the `\MakinaCorpus\Profiling\Implementation\ProfilerContextAwareTrait`
you allow your code to be resilient in case of misinitialisation:

 - If the autoconfiguration failed, it will create a default null instance doing
   nothing, which will have a near-to-zero performance impact.

 - If the bundle is deactivated, it will create a default null instance doing
   nothing, which will have a near-to-zero performance impact.

You then can use the profiler using the injected context:

```php
namespace MyVendor\MyApp\SomeNamespace;

use MakinaCorpus\Profiling\Implementation\ProfilerContextAware;
use MakinaCorpus\Profiling\Implementation\ProfilerContextAwareTrait;

/**
 * Implementing the interface allows autoconfiguration.
 */
class SomeService implements ProfilerContextAware
{
    /**
     * Using the trait provides a default working implementation.
     */
    use ProfilerContextAwareTrait;

    public function doSomething()
    {
        $profiler = $this->getProfilerContext()->start('something');

        try {
            $profiler->start('something-else');
            $this->doSomethingElse();
            $profiler->stop('something-else');

            $profiler->start('something-other');
            $this->doSomethingElse();
            $profiler->stop('something-other');

            $profiler->start('something-that-fails');
            throw new \Exception("Oups, something bad happened.");
            $profiler->stop('something-that-fails');

        } finally {
            // We do heavily recommend that use the try/finally
            // pattern to ensure that exceptions will not betry
            // your profilers.
            // The last stop() call within the try block will never
            // be called, by stopping the parent profiler here, it
            // stops the child as well.
            $profiler->stop();
        }
    }
}
```

And that's it, have fun !

## Stopwatch component

If you are working on a debug enabled environment, this component will plug
itself onto the Symfony stopwatch component transparently.

This means that all of your timers will appear in the profiler toolbar a no
additional cost.

# To-do list

- add memory profiling in `Profiler`,
- transparent `sentry/sentry` bridge for sending results in Sentry.

