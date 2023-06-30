<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony5\DependencyInjection;

use MakinaCorpus\Profiling\ProfilerContext;
use MakinaCorpus\Profiling\Bridge\Symfony5\Command\StoreClearCommand;
use MakinaCorpus\Profiling\Handler\NamedTraceHandler;
use MakinaCorpus\Profiling\Handler\SentryHandler;
use MakinaCorpus\Profiling\Handler\StoreHandler;
use MakinaCorpus\Profiling\Handler\StreamHandler;
use MakinaCorpus\Profiling\Handler\SymfonyStopwatchHandler;
use MakinaCorpus\Profiling\Handler\TraceHandlerDecorator;
use MakinaCorpus\Profiling\Handler\TriggerHandlerDecorator;
use MakinaCorpus\Profiling\ProfilerContext\DefaultProfilerContext;
use MakinaCorpus\Profiling\ProfilerContext\MemoryProfilerContext;
use MakinaCorpus\Profiling\ProfilerContext\NullProfilerContext;
use MakinaCorpus\Profiling\ProfilerContext\TracingProfilerContextDecorator;
use MakinaCorpus\Profiling\Store\GoatQueryTraceStore;
use MakinaCorpus\Profiling\Store\TraceStoreRegistry;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
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
        if (\in_array(WebProfilerBundle::class, $container->getParameter('kernel.bundles'))) {
            $profilerContext = new Definition();
            $profilerContext->setClass(MemoryProfilerContext::class);
        } else {
            $profilerContext = new Definition();
            $profilerContext->setClass(DefaultProfilerContext::class);
        }

        $profilerContext->addMethodCall('toggle', [new Parameter('profiling.enabled')]);
        // Allow container to purge memory on terminate. This will also help
        // when running long-running CLI commands, such as a message bus
        // consumer.
        $profilerContext->addTag('kernel.reset', ['method' => 'flush']);
        $container->setDefinition('profiling.context.default', $profilerContext);
        $container->setAlias(ProfilerContext::class, 'profiling.context.default');

        $this->configureHandlers($container, $config);
        $this->registerCommands($container, $config);
    }

    private function parseMemoryString(string $value): int
    {
        $matches = [];
        if (!\preg_match('/(\d+(\.\d+|))\s*([KMG]|)/', $value, $matches)) {
            throw new InvalidArgumentException(\sprintf("Invalid byte quantity string, expected: 'x.y[K|M|G]', got: '%s'", $value));
        }
        $value = (float) $matches[1];
        if ($matches[3]) {
            switch ($matches[3]) {
                case 'K':
                    return (int) \round($value * 1024);
                case 'M':
                    return (int) \round($value * 1024 * 1024);
                case 'G':
                    return (int) \round($value * 1024 * 1024 * 1024);
            }
        }
        return (int) \round($value);
    }

    private function registerCommands(ContainerBuilder $container, array $config)
    {
        if (\class_exists(Command::class)) {
            $storeClearDefinition = new Definition();
            $storeClearDefinition->setClass(StoreClearCommand::class);
            $storeClearDefinition->setArguments([new Reference(TraceStoreRegistry::class)]);
            $storeClearDefinition->addTag('console.command');
            $container->setDefinition(StoreClearCommand::class, $storeClearDefinition);
        }
    }

    private function configureHandlers(ContainerBuilder $container, array $config)
    {
        $storeNames = [];
        $handlerChannelMap = [];
        $handlerReferences = [];

        foreach ($config['handlers'] ?? [] as $name => $options) {
            $serviceId = 'profiling.handler.' . $name;

            if (empty($options['type'])) {
                if (empty($options['store'])) {
                    throw new InvalidArgumentException(\sprintf("Handler '%s': is missing 'type'.", $name));
                }
                $options['type'] = 'store';
            }

            switch ($options['type']) {

                case 'file':
                    $definition = new Definition();
                    $definition->setClass(StreamHandler::class);
                    $filePermission = null;
                    if (isset($options['file_permission'])) {
                        if (!\is_int($options['file_permission']) || !\ctype_digit($options['file_permission'])) {
                            throw new InvalidArgumentException(\sprintf("Handler '%s': options 'file_permission' must be a permission integer.", $name));
                        }
                        $filePermission = (int)$options['file_permission'];
                    }
                    $definition->setArguments([$options['path'], $filePermission, $options['file_lock'] ?? false]);
                    break;

                case 'sentry':
                case 'sentry4':
                    $definition = new Definition();
                    $definition->setClass(SentryHandler::class);
                    $definition->setArguments([new Reference(HubInterface::class)]);
                    break;

                case 'service':
                    if (!isset($options['id'])) {
                        throw new InvalidArgumentException(\sprintf("Handler '%s': option 'id' id required when type is 'service'.", $name));
                    }
                    $definition = new Definition();
                    $definition->setClass(TraceHandlerDecorator::class);
                    $definition->setArguments([new Reference($options['id'])]);
                    break;

                case 'stopwatch':
                    $definition = new Definition();
                    $definition->setClass(SymfonyStopwatchHandler::class);
                    $definition->setArguments([new Reference(Stopwatch::class)]);
                    break;

                case 'store':
                    $definition = new Definition();
                    $definition->setClass(StoreHandler::class);
                    $storeServiceId = $serviceId . '.store';

                    if (empty($options['store'])) {
                        throw new InvalidArgumentException(\sprintf("Handler '%s': is missing 'store'.", $name));
                    }

                    switch ($options['store']) {

                        case 'goat-query':
                            if (empty($options['store_uri'])) {
                                throw new InvalidArgumentException(\sprintf("Handler '%s': is missing 'store_uri'.", $name, $options['store']));
                            }

                            $storeDefinition = new Definition();
                            $storeDefinition->setClass(GoatQueryTraceStore::class);
                            $storeDefinition->setArguments([
                                $options['store_uri'],
                                $options['store_table'] ?? null
                            ]);
                            $container->setDefinition($storeServiceId, $storeDefinition);

                            $definition->setArguments([new Reference($storeServiceId)]);
                            $storeNames[$name] = new Reference($storeServiceId);
                            break;

                        default:
                            throw new InvalidArgumentException(\sprintf("Handler '%s': store '%s' is not supported.", $name, $options['store']));
                    }
                    break;

                default:
                    throw new InvalidArgumentException(\sprintf("Handler '%s': type '%s' is not supported.", $name, $options['type']));
            }

            $className = $definition->getClass();
            if (\is_subclass_of($className, NamedTraceHandler::class)) {
                $definition->addMethodCall('setName', [$name]);
            }

            if (isset($options['threshold'])) {
                $hasThreshold = false;
                $memoryThreshold = null;
                $timeThreshold = null;
                if (isset($options['threshold']['memory'])) {
                    $hasThreshold = true;
                    $memoryThreshold = $this->parseMemoryString($options['threshold']['memory']);
                }
                if ($options['threshold']['time']) {
                    $hasThreshold = true;
                    $timeThreshold = (float) $options['threshold']['time'];
                }
                if ($hasThreshold) {
                    $definition->addMethodCall('setThreshold', [$memoryThreshold, $timeThreshold]);
                }
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

            if ($triggerName = $options['trigger']) {
                $decorator = new Definition();
                $decorator->setClass(TriggerHandlerDecorator::class);
                $decorator->setArguments([new Reference('.inner'), $triggerName]);
                $decorator->setDecoratedService($serviceId);
            }

            $handlerReferences[$name] = new Reference($serviceId);
        }

        // Do not register the main context if there are no handlers.
        if ($handlerReferences) {
            $tracingContextDecoratorDefinition = new Definition();
            $tracingContextDecoratorDefinition->setClass(TracingProfilerContextDecorator::class);
            $tracingContextDecoratorDefinition->setArguments([new Reference('.inner'), $handlerReferences, $handlerChannelMap]);
            $tracingContextDecoratorDefinition->setDecoratedService(ProfilerContext::class);
            $container->setDefinition(TracingProfilerContextDecorator::class, $tracingContextDecoratorDefinition);
        }

        // Register store registry for commands.
        $storeRegistryDefinition = new Definition();
        $storeRegistryDefinition->setClass(ContainerTraceStoreRegistry::class);
        $storeRegistryDefinition->setArguments([\array_keys($storeNames), ServiceLocatorTagPass::register($container, $storeNames)]);
        $container->setDefinition(ContainerTraceStoreRegistry::class, $storeRegistryDefinition);
        $container->setAlias(TraceStoreRegistry::class, ContainerTraceStoreRegistry::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new ProfilingConfiguration();
    }
}
