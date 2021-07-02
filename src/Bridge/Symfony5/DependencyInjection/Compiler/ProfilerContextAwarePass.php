<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony5\DependencyInjection\Compiler;

use MakinaCorpus\Profiling\ProfilerContext;
use MakinaCorpus\Profiling\Implementation\ProfilerContextAware;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

final class ProfilerContextAwarePass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        foreach (\array_keys($container->findTaggedServiceIds('profiling.profiler_aware', true)) as $id) {
            $definition = $container->getDefinition($id);
            $class = $definition->getClass();

            if (!$reflexion = $container->getReflectionClass($class)) {
                throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }
            if (!$reflexion->implementsInterface(ProfilerContextAware::class)) {
                throw new InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, ProfilerContextAware::class));
            }

            $definition->addMethodCall('setProfilerContext', [new Reference(ProfilerContext::class)]);
        }
    }
}
