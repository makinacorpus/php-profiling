<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Handler\Formatter;

use MakinaCorpus\Profiling\ProfilerTrace;
use MakinaCorpus\Profiling\Handler\Formatter;
use MakinaCorpus\Profiling\Helper\WithPidTrait;

class JsonFormatter implements Formatter
{
    use WithPidTrait;

    private bool $started = false;

    /**
     * {@inheritdoc}
     */
    public function format(ProfilerTrace $trace): string
    {
        if (!$this->started) {
            $this->started = true;
        }

        return \json_encode([
            'pid' => $this->getPid(),
            'created' => (new \DateTimeImmutable())->format(\DateTime::ISO8601),
            'id' => $trace->getId(),
            'name' => $trace->getAbsoluteName(),
            'relname' => $trace->getName(),
            'time' => $trace->getElapsedTime(),
            'timenano' => $trace->getElapsedTimeNano(),
            'mem' => $trace->getMemoryUsage(),
            'description' => $trace->getDescription(),
            'channels' => $trace->getChannels(),
            'attributes' => $trace->getAttributes(),
        ]);
    }
}
