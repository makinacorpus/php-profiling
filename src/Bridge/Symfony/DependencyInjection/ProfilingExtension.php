<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony\DependencyInjection;

use MakinaCorpus\Profiling\Bridge\Symfony\Command\StoreClearCommand;
use MakinaCorpus\Profiling\Handler\NamedTraceHandler;
use MakinaCorpus\Profiling\Handler\SentryHandler;
use MakinaCorpus\Profiling\Handler\StoreHandler;
use MakinaCorpus\Profiling\Handler\StreamHandler;
use MakinaCorpus\Profiling\Handler\SymfonyStopwatchHandler;
use MakinaCorpus\Profiling\Handler\TraceHandlerDecorator;
use MakinaCorpus\Profiling\Handler\TriggerHandlerDecorator;
use MakinaCorpus\Profiling\Matcher;
use MakinaCorpus\Profiling\Profiler;
use MakinaCorpus\Profiling\Profiler\DefaultProfiler;
use MakinaCorpus\Profiling\Profiler\NullProfiler;
use MakinaCorpus\Profiling\Profiler\TracingProfilerDecorator;
use MakinaCorpus\Profiling\Prometheus\Schema\ArraySchema;
use MakinaCorpus\Profiling\Prometheus\Storage\QueryBuilderStorage;
use MakinaCorpus\Profiling\Store\QueryBuilderTraceStore;
use MakinaCorpus\Profiling\Store\TraceStoreRegistry;
use Sentry\State\HubInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Yaml\Yaml;

final class ProfilingExtension extends Extension
{
    #[\Override]
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        // Configuration killswitch.
        if (!$config['enabled']) {
            $profiler = new Definition();
            $profiler->setClass(NullProfiler::class);
            $container->setDefinition(NullProfiler::class, $profiler);
            $container->setAlias(Profiler::class, NullProfiler::class);
            return;
        }

        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
        $loader->load('services.yaml');

        $container->setParameter('env(PROFILING_ENABLE)', "1");
        $container->setParameter('profiling.enabled', "%env(bool:PROFILING_ENABLE)%");

        $profiler = new Definition();
        $profiler->setClass(DefaultProfiler::class);
        $profiler->setArgument('$sampleLogger', new Reference('profiling.prometheus.sample_logger'));
        $profiler->addMethodCall('toggle', [new Parameter('profiling.enabled')]);
        // Allow container to purge memory on terminate. This will also help
        // when running long-running CLI commands, such as a message bus
        // consumer.
        $profiler->addTag('kernel.reset', ['method' => 'exitContext']);
        $container->setDefinition('profiling.profiler', $profiler);
        $container->setAlias(Profiler::class, 'profiling.profiler');

        $this->configureHandlers($container, $config);
        $this->registerCommands($container, $config);

        if ($config['prometheus']['enabled'] ?? false) {
            $this->registerPrometheus($config['prometheus'] ?? [], $container);
            $profiler->setArgument('$prometheusEnabled', true);
        }
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

                        case 'query-builder':
                            if (empty($options['store_uri'])) {
                                throw new InvalidArgumentException(\sprintf("Handler '%s': is missing 'store_uri'.", $name));
                            }

                            $storeDefinition = new Definition();
                            $storeDefinition->setClass(QueryBuilderTraceStore::class);
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

        // Do not register the main profiler if there are no handlers.
        if ($handlerReferences) {
            $tracingDecoratorDefinition = new Definition();
            $tracingDecoratorDefinition->setClass(TracingProfilerDecorator::class);
            $tracingDecoratorDefinition->setArguments([new Reference('.inner'), $handlerReferences, $handlerChannelMap]);
            $tracingDecoratorDefinition->setDecoratedService(Profiler::class);
            $container->setDefinition(TracingProfilerDecorator::class, $tracingDecoratorDefinition);
        }

        // Register store registry for commands.
        $storeRegistryDefinition = new Definition();
        $storeRegistryDefinition->setClass(ContainerTraceStoreRegistry::class);
        $storeRegistryDefinition->setArguments([\array_keys($storeNames), ServiceLocatorTagPass::register($container, $storeNames)]);
        $container->setDefinition(ContainerTraceStoreRegistry::class, $storeRegistryDefinition);
        $container->setAlias(TraceStoreRegistry::class, ContainerTraceStoreRegistry::class);
    }

    private function registerPrometheus(array $config, ContainerBuilder $container)
    {
        $kernelBundles = $container->getParameter('kernel.bundles');

        $storageType = $config['storage']['type'] ?? 'in_memory';
        $storageUri = $config['storage']['uri'] ?? null;
        $storageOptions = $config['storage']['options'] ?? [];

        /*
        if (\in_array(CoreBusBundle::class, $kernelBundles)) {
            $loader->load('services.corebus.yaml');
        }
        if (\in_array(ProfilingBundle::class, $kernelBundles)) {
            $loader->load('services.profiling.yaml');
        }
         */

        // Ignore lists.
        if (isset($config['request_ignore_methods'])) {
            $container->setParameter('profiling.prometheus.request_ignored_methods', $config['request_ignore_methods']);
        }
        if (isset($config['request_ignore_routes'])) {
            $matcher = new Matcher();
            foreach ($config['request_ignore_routes'] as $pattern) {
                $matcher->addPattern($pattern);
            }
            $container->getDefinition('profiling.prometheus.matcher.route')->setArgument(0, $matcher->getCompiledRegex());
        }
        if (isset($config['console_ignore'])) {
            $matcher = new Matcher();
            foreach ($config['console_ignore'] as $pattern) {
                $matcher->addPattern($pattern, \str_ends_with($pattern, ':'));
            }
            $container->getDefinition('profiling.prometheus.matcher.console')->setArgument(0, $matcher->getCompiledRegex());
        }

        $storageDefinition = match ($storageType) {
            'apcng' => $this->registerPrometheusStorageApcng($container, $storageUri, $storageOptions),
            'apcu' => $this->registerPrometheusStorageApcu($container, $storageUri, $storageOptions),
            'query_builder' => $this->registerPrometheusStorageQueryBuilder($container, $storageUri, $storageOptions),
            'in_memory' => $this->registerPrometheusStorageInMemory($container, $storageUri, $storageOptions),
            'redis' => $this->registerPrometheusStorageRedis($container, $storageUri, $storageOptions),
            default => throw new InvalidArgumentException(\sprintf("Storage '%s' is not a supported storage.", $storageType)),
        };

        $container->removeDefinition('profiling.prometheus.storage');
        $container->setDefinition('profiling.prometheus.storage', $storageDefinition);

        $this->registerPrometheusSchema($container, $config['schema'] ?? []);
    }

    private function getPrometheusDefaultSchemaAsArray(ContainerBuilder $container): array
    {
        return Yaml::parseFile(\dirname(__DIR__).'/Resources/config/packages/profiling.prometheus.schema.yaml')['profiling']['prometheus']['schema'] ?? [];
    }

    private function registerPrometheusSchema(ContainerBuilder $container, array $userSchema): void
    {
        $schema = $this->getPrometheusDefaultSchemaAsArray($container);

        foreach ($userSchema as $name => $def) {
            if (isset($schema[$name])) {
                throw new InvalidArgumentException(\sprintf("You cannot override default schema, given: '%s'", $name));
            }

            // @todo Validate schema.
            $schema[$name] = $def;
        }

        $definition = new Definition();
        $definition->setClass(ArraySchema::class);
        $definition->setArguments([new Parameter('profiling.prometheus.namespace'), $schema, new Parameter('kernel.debug')]);

        $container->setDefinition('profiling.prometheus.schema', $definition);
    }

    private function registerPrometheusStorageApcng(ContainerBuilder $container, ?string $uri, array $options): Definition
    {
        throw new InvalidArgumentException("Not implemented yet.");
    }

    private function registerPrometheusStorageApcu(ContainerBuilder $container, ?string $uri, array $options): Definition
    {
        throw new InvalidArgumentException("Not implemented yet.");
    }

    private function registerPrometheusStorageQueryBuilder(ContainerBuilder $container, ?string $uri, array $options): Definition
    {
        if (!$uri) {
            throw new InvalidArgumentException("Storage 'query_builder' requires a database URI.");
        }

        $definition = new Definition();
        $definition->setClass(QueryBuilderStorage::class);
        $definition->setArgument(0, $uri);

        if ($table = ($options['table'] ?? null)) {
            $definition->setArgument(1, $table);
        }

        return $definition;
    }

    private function registerPrometheusStorageInMemory(ContainerBuilder $container, ?string $uri, array $options): Definition
    {
        if ($uri) {
            throw new InvalidArgumentException("Storage 'in_memory' does not require an URI.");
        }

        throw new InvalidArgumentException("Not implemented yet.");
    }

    private function registerPrometheusStorageRedis(ContainerBuilder $container, ?string $uri, array $options): Definition
    {
        throw new InvalidArgumentException("Not implemented yet.");
    }

    #[\Override]
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new ProfilingConfiguration();
    }
}
