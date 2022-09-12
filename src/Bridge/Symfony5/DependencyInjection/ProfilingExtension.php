<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony5\DependencyInjection;

use MakinaCorpus\Profiling\ProfilerContext;
use MakinaCorpus\Profiling\Handler\SentryHandler;
use MakinaCorpus\Profiling\Handler\SymfonyStopwatchHandler;
use MakinaCorpus\Profiling\Implementation\MemoryProfilerContext;
use MakinaCorpus\Profiling\Implementation\NullProfilerContext;
use MakinaCorpus\Profiling\Implementation\TracingProfilerContextDecorator;
use Sentry\State\HubInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Stopwatch\Stopwatch;

final class ProfilingExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        // Configuration killswitch.
        if (!$config['enabled']) {
            $profilerContext = new Definition();
            $profilerContext->setClass(NullProfilerContext::class);
            $container->setDefinition(NullProfilerContext::class, $profilerContext);
            $container->setAlias(ProfilerContext::class, NullProfilerContext::class);
            return;
        }

        $container->setParameter('env(PROFILING_ENABLE)', "1");
        $container->setParameter('profiling.enabled', "%env(bool:PROFILING_ENABLE)%");

        // Default profiler context, acts as a factory of profilers.
        $profilerContext = new Definition();
        $profilerContext->setClass(MemoryProfilerContext::class);
        $container->setDefinition(MemoryProfilerContext::class, $profilerContext);
        $container->setAlias(ProfilerContext::class, MemoryProfilerContext::class);

        $this->configureHandlers($container, $config);
    }

    private function configureHandlers(ContainerBuilder $container, array $config)
    {
        $handlerChannelMap = [];
        $handlerReferences = [];

        foreach ($config['handlers'] ?? [] as $name => $options) {
            $definition = new Definition();
            $serviceId = 'profiling.handler.' . $name;

            switch ($options['type']) {

                case 'sentry':
                case 'sentry4':
                    $definition->setClass(SentryHandler::class);
                    $definition->setArguments([new Reference(HubInterface::class)]);
                    break;

                case 'stopwatch':
                    $definition->setClass(SymfonyStopwatchHandler::class);
                    $definition->setArguments([new Reference(Stopwatch::class)]);
                    break;

                default:
                    throw new InvalidArgumentException(\sprintf("Handler '%s': type '%s' is not supported.", $name, $options['type']));
            }

            if (isset($options['channels'])) {
                if (\is_string($options['channels'])) {
                    $channels = [$options['channels']];
                } else if (\is_array($options['channels'])) {
                    $channels = $options['channels'];
                } else {
                    throw new InvalidArgumentException(\sprintf("Handler '%s': 'channels' must be a string or an array of string.", $name));
                }

                if ($channels) {
                    $handlerChannelMap[$name] = \array_values(\array_unique($channels));
                }
            }

            $container->setDefinition($serviceId, $definition);
            $handlerReferences[] = new Reference($serviceId);
        }

        if ($handlerReferences) {
            $tracingContextDecoratorDefinition = new Definition();
            $tracingContextDecoratorDefinition->setClass(TracingProfilerContextDecorator::class);
            $tracingContextDecoratorDefinition->setArguments([new Reference('.inner'), $handlerReferences, $handlerChannelMap]);
            $tracingContextDecoratorDefinition->setDecoratedService(ProfilerContext::class);
            $container->setDefinition(TracingProfilerContextDecorator::class, $tracingContextDecoratorDefinition);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new ProfilingConfiguration();
    }
}
