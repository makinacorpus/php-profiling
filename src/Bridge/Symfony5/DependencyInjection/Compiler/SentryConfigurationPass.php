<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony5\DependencyInjection\Compiler;

use MakinaCorpus\Profiling\Bridge\Sentry4\Tracing\TracingProfilerContextDecorator;
use Sentry\State\HubInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

final class SentryConfigurationPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition(TracingProfilerContextDecorator::class) && !$container->hasDefinition(HubInterface::class)) {
            $container->removeDefinition(TracingProfilerContextDecorator::class);
        }
    }
}
