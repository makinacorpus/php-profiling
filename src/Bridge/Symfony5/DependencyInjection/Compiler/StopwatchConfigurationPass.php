<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony5\DependencyInjection\Compiler;

use MakinaCorpus\Profiling\Bridge\Symfony5\Stopwatch\StopwatchProfilerContextDecorator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

final class SentryConfigurationPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition(StopwatchProfilerContextDecorator::class) && !$container->hasDefinition('debug.stopwatch')) {
            $container->removeDefinition(StopwatchProfilerContextDecorator::class);
        }
    }
}
