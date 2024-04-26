# Changelog

## Next

This feature is a major upgrade of this component. Whereas the profiling
API didn't have major changes and should remain (mostly) API compatible,
PHP version and dependency requirements have been seriously overhauled.

This release changes all class and interfaces names, porting will require
you to pass over all code using it. Using static analysis tooling will
greatly help you in that regard.

If you don't want to rewrite all you code, please consider sticking to
2.0 release, nevertheless you must know that it will be minimally
maintained.

This new version brings an experimental Prometheus data collector and
scrapping API.

* [feature] ⭐️ Add Prometheus sample low-level API.
* [feature] ⭐️ Add Prometheus sample logger and storage.
* [feature] ⭐️ Add Prometheus controller endpoint for data scrapping.
* [bc] ⚠️ Rename `MakinaCorpus\Profiling\ProfilerAwareTrait::startProfiler()`
  method to `startTimer()`.
* [bc] ⚠️ Rename `MakinaCorpus\Profiling\ProfilerContextAwareTrait` to
  `MakinaCorpus\Profiling\ProfilerAwareTrait`.
* [bc] ⚠️ Rename `MakinaCorpus\Profiling\ProfilerContextAware` to
  `MakinaCorpus\Profiling\ProfilerAware`.
* [bc] ⚠️ Rename `MakinaCorpus\Profiling\Profiler::start()` method to `timer()`.
* [bc] ⚠️ Rename `MakinaCorpus\Profiling\Profiler::create()` method to `createTimer()`.
* [bc] ⚠️ Remove `MakinaCorpus\Profiling\Profiler::getAllProfilers()` method.
* [bc] ⚠️ Rename `MakinaCorpus\Profiling\ProfilerContext` to
  `MakinaCorpus\Profiling\Profiler` which is now the main entry point for users.
* [bc] ⚠️ Rename `MakinaCorpus\Profiling\Profiler` to
  `MakinaCorpus\Profiling\Timer` and change all implementation names accordingly.
* [bc] ⚠️ Drop `makinacorpus/goat-query` dependency in favor of using
  `makinacorpus/query-builder` instead.
* [bc] ⚠️ Raised Symfony requirement to 6.0 or 7.0.
* [fix] Prometheus controller can now work without the security component.

## 2.0.8

Changelog starts here.
