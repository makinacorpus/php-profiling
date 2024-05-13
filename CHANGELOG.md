# Changelog

## Next

This release is a full rewrite of the previous existing code, while it
retains the existing functionality and adds new one, API class and method
names have changed.

Prometheus counter, gauge and summary has been added, with metrics storage
in SQL database and restitution throught an URL endpoint. Default metrics
feature http request counters and timings, error reporting, console command
metrics, and basic system information monitoring. Prometheus metrics schema
is extensible, users can add their own metrics easily.

All dependencies have been raised to decent versions.

There is no upgrade guide since almost every name has been changed,
nevertheless if you used the legacy `Profiler` and `ProfilerContext` API
you may just need to rename your use import to respectively `Timer` and
`Profiler` instead.

* [feature] ⭐️ Add Prometheus various metrics, system information, user
  configurable schema, and metrics scrapping endpoint.
* [bc] ⚠️ Drop `makinacorpus/goat-query` optional dependency in favor of using
  `makinacorpus/query-builder` instead for metrics storage.
* [bc] ⚠️ Raised Symfony requirement to 6.0 or 7.0.

## 2.0.8

Changelog starts here.
