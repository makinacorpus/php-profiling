<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Implementation;

use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\ProfilerContext;

/**
 * This specific instance will be injected in place of the default configured
 * ProfilerContext instance in each service that configures channels for its
 * timers. It will attach channels to created profiler instances.
 */
final class DispatchProfilerContextDecorator extends AbstractProfilerContextDecorator
{
    /** @var string[] */
    private array $channels;

    /**
     * @param string[] $channels
     */
    public function __construct(ProfilerContext $decorated, array $channels)
    {
        parent::__construct($decorated);

        $this->channels = \array_unique($channels);
    }

    /**
     * {@inheritdoc}
     */
    public function start(?string $name = null, ?array $channels = null): Profiler
    {
        return $this->decorated->start($name, $this->channels);
    }
}
