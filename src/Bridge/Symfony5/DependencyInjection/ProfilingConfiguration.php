<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony5\DependencyInjection;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

final class ProfilingConfiguration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('profiling');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->booleanNode('enabled')
                    ->info("Global killswitch for disabling profiling, set this to false to completly disable profiling.")
                    ->defaultTrue()
                ->end()
                ->arrayNode('stopwatch')
                    ->children()
                        ->booleanNode('enabled')
                            ->info("Enable stopwatch decorator, default is true, but only if the @debug.stopwatch service is present in container.")
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('sentry')
                    ->children()
                        ->booleanNode('enabled')
                            ->info("Enable sentry decorator, it can only be enabled if the 'sentry/sentry-symfony' is installed and sentry bundle is enabled.")
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
