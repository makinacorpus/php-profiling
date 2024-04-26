# Changelog

## Next

This feature is a major upgrade of this component, almost all components
have been renamed. PHP version and dependency requirements have been raised
as well.

This release changes all class and interfaces names, porting will require
you to pass over all code using it. Using static analysis tooling will
greatly help you in that regard.

This new version brings an experimental Prometheus data collector and
scrapping API. This is still experimental, implementation will be polished
in a near future.

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
* [bc] ⚠️ Drop `makinacorpus/goat-query` optional dependency in favor of using
  `makinacorpus/query-builder` instead for metrics storage.
* [bc] ⚠️ Raised Symfony requirement to 6.0 or 7.0.
* [fix] Prometheus controller can now work without the security component.

## 2.0.8

Changelog starts here.
