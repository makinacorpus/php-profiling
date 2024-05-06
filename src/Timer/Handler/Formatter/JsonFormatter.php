<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Timer\Handler\Formatter;

use MakinaCorpus\Profiling\Helper\WithPidTrait;
use MakinaCorpus\Profiling\Timer\Handler\Formatter;
use MakinaCorpus\Profiling\Timer\TimerTrace;

class JsonFormatter implements Formatter
{
    use WithPidTrait;

    private bool $started = false;

    #[\Override]
    public function format(TimerTrace $trace): string
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
