<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Error;

class InvalidLabelValuesError extends \InvalidArgumentException implements PrometheusError
{
}
