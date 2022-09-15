<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony5\DependencyInjection\Compiler;

use MakinaCorpus\Profiling\ProfilerContext;
use MakinaCorpus\Profiling\ProfilerContextAware;
use MakinaCorpus\Profiling\ProfilerContext\DispatchProfilerContextDecorator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
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
        foreach ($container->findTaggedServiceIds('profiling.profiler_aware', true) as $id => $tags) {
            $definition = $container->getDefinition($id);
            $class = $definition->getClass();

            if (!$reflexion = $container->getReflectionClass($class)) {
                throw new InvalidArgumentException(\sprintf("Class '%s' used for service '%s' cannot be found.", $class, $id));
            }
            if (!$reflexion->implementsInterface(ProfilerContextAware::class)) {
                throw new InvalidArgumentException(\sprintf("Service '%s' must implement interface '%s'.", $id, ProfilerContextAware::class));
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

                $serviceId = $id . '.profiler_context';
                $decoratorDefinition = new Definition();
                $decoratorDefinition->setClass(DispatchProfilerContextDecorator::class);
                $decoratorDefinition->setArguments([new Reference(ProfilerContext::class), $channels]);
                $container->setDefinition($serviceId, $decoratorDefinition);

                $definition->addMethodCall('setProfilerContext', [new Reference($serviceId)]);
            } else {
                $definition->addMethodCall('setProfilerContext', [new Reference(ProfilerContext::class)]);
            }
        }
    }
}
