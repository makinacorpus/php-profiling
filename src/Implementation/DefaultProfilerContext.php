<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Implementation;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerContext;

final class DefaultProfilerContext implements ProfilerContext
{
    /** @var Profiler */
    private array $profilers = [];

    /**
     * {@inheritdoc}
     */
    public function start(?string $name = null): Profiler
    {
        return $this->profilers[] = new DefaultProfiler($name);
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        return !empty($this->profilers);
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): iterable
    {
        $ret = $this->profilers;
        // Early reset, if later PHP becomes asynchronous, the context instance
        // can start being re-used right up now, while profilers are being
        // stopped in the background.
        $this->profilers = [];

        foreach ($ret as $profiler) {
            $profiler->stop();
        }

        return $ret;
    }
}
