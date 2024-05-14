<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Error;

class SchemaError extends \RuntimeException implements PrometheusError
{
}
