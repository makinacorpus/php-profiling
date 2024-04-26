<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Error;

class StorageError extends \RuntimeException implements PrometheusError
{
}
