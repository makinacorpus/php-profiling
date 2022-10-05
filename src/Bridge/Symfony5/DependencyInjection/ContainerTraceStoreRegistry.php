<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony5\DependencyInjection;

use MakinaCorpus\Profiling\TraceStore;
use MakinaCorpus\Profiling\Store\TraceStoreRegistry;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ContainerTraceStoreRegistry implements TraceStoreRegistry
{
    private array $names;
    private ServiceLocator $serviceLocator;

    public function __construct(array $names, ?ServiceLocator $serviceLocator = null)
    {
        $this->names = $names;
        $this->serviceLocator = $serviceLocator ?? new ServiceLocator([]);
    }

    /**
     * {@inheritdoc}
     */
    public function list(): array
    {
        return $this->names;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name): TraceStore
    {
        if ($this->serviceLocator->has($name)) {
            return $this->serviceLocator->get($name);
        }

        throw new \InvalidArgumentException(\sprintf("Trace store '%s' does not exist.", $name));
    }
}
