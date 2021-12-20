<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony5\DependencyInjection;

use MakinaCorpus\Profiling\ProfilerContext;
use MakinaCorpus\Profiling\Bridge\Sentry4\Tracing\TracingProfilerContextDecorator;
use MakinaCorpus\Profiling\Bridge\Symfony5\Stopwatch\StopwatchProfilerContextDecorator;
use MakinaCorpus\Profiling\Implementation\DefaultProfilerContext;
use Sentry\State\HubInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Parameter;

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
            return;
        }

        $container->setParameter('env(PROFILING_ENABLE)', "1");
        $container->setParameter('profiling.enabled', "%env(bool:PROFILING_ENABLE)%");

        $profilerContext = new Definition();
        $profilerContext->setClass(DefaultProfilerContext::class);
        $container->setDefinition(DefaultProfilerContext::class, $profilerContext);
        $container->setAlias(ProfilerContext::class, DefaultProfilerContext::class);

        if ($config['stopwatch']['enabled'] ?? false) {
            $decoratedInnerId = StopwatchProfilerContextDecorator::class . '.inner';
            $decoratorDef = new Definition();
            $decoratorDef->setClass(StopwatchProfilerContextDecorator::class);
            $decoratorDef->setDecoratedService(DefaultProfilerContext::class, $decoratedInnerId, 800);
            $decoratorDef->setArguments([new Reference('debug.stopwatch'), new Reference($decoratedInnerId)]);
            $decoratorDef->addMethodCall('toggle', [new Parameter('profiling.enabled')]);
            $container->setDefinition(StopwatchProfilerContextDecorator::class, $decoratorDef);
        }

        if ($config['sentry']['enabled'] ?? false) {
            $decoratedInnerId = TracingProfilerContextDecorator::class . '.inner';
            $decoratorDef = new Definition();
            $decoratorDef->setClass(TracingProfilerContextDecorator::class);
            $decoratorDef->setDecoratedService(DefaultProfilerContext::class, $decoratedInnerId, 800);
            $decoratorDef->setArguments([new Reference(HubInterface::class), new Reference($decoratedInnerId)]);
            $decoratorDef->addMethodCall('toggle', [new Parameter('profiling.enabled')]);
            $container->setDefinition(TracingProfilerContextDecorator::class, $decoratorDef);
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
