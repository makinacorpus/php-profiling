<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Profiler;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\Timer;

/**
 * This specific instance will be injected in place of the default configured
 * Profiler instance in each service that configures channels for its timers.
 * It will attach channels to created timer instances.
 */
final class DispatchProfilerDecorator extends AbstractProfilerDecorator
{
    /** @var string[] */
    private array $channels;

    /**
     * @param string[] $channels
     */
    public function __construct(Profiler $decorated, array $channels)
    {
        parent::__construct($decorated);

        $this->channels = \array_unique($channels);
    }

    #[\Override]
    public function createTimer(?string $name = null, ?array $channels = null): Timer
    {
        if ($channels) {
            foreach ($this->channels as $channel) {
                $channels[] = $channel;
            }
        } else {
            $channels = $this->channels;
        }

        return $this->decorated->createTimer($name, $channels);
    }
}
