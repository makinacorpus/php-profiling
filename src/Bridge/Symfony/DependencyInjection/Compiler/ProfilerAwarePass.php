<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony\DependencyInjection\Compiler;

use MakinaCorpus\Profiling\ProfilerAware;
use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\Profiler\DispatchProfilerDecorator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

final class ProfilerAwarePass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container)
    {
        foreach ($container->findTaggedServiceIds('profiling.profiler_aware', true) as $id => $tags) {
            $definition = $container->getDefinition($id);
            $class = $definition->getClass();

            if (!$reflexion = $container->getReflectionClass($class)) {
                throw new InvalidArgumentException(\sprintf("Class '%s' used for service '%s' cannot be found.", $class, $id));
            }
            if (!$reflexion->implementsInterface(ProfilerAware::class)) {
                throw new InvalidArgumentException(\sprintf("Service '%s' must implement interface '%s'.", $id, ProfilerAware::class));
            }

            $channels = [];
            foreach ($tags as $attributes) {
                foreach (['channel', 'channels'] as $attrName) {
                    $attrValue = $attributes[$attrName] ?? null;
                    if ($attrValue) {
                        if (\is_string($attrValue)) {
                            $channels[] = $attrValue;
                        } else if (\is_array($attrValue)) {
                            foreach ($attrValue as $channel) {
                                $channels[] = $channel;
                            }
                        } else {
                            throw new InvalidArgumentException(\sprintf("Service '%s', tag '%s', attribute '%s' must be a string or an array of string .", $id, 'profiling.profiler_aware', $attrName));
                        }
                    }
                }
            }

            if ($channels) {
                $channels = \array_unique($channels);

                $serviceId = $id . '.profiler';
                $decoratorDefinition = new Definition();
                $decoratorDefinition->setClass(DispatchProfilerDecorator::class);
                $decoratorDefinition->setArguments([new Reference(Profiler::class), $channels]);
                $container->setDefinition($serviceId, $decoratorDefinition);

                $definition->addMethodCall('setProfiler', [new Reference($serviceId)]);
            } else {
                $definition->addMethodCall('setProfiler', [new Reference(Profiler::class)]);
            }
        }
    }
}
