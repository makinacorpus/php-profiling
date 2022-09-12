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
                ->arrayNode('handlers')
                    ->normalizeKeys(true)
                    ->prototype('array')
                        ->children()
                            // If null, then string
                            ->variableNode('channels')->end()
                            ->scalarNode('date_format')->defaultNull()->end()
                            ->scalarNode('path')->defaultNull()->end()
                            ->scalarNode('trigger')->defaultNull()->end()
                            ->scalarNode('type')->end()
                        ->end()
                    ->end()
                ->end()
                ->variableNode('stopwatch')
                    ->info("Deprecated, stopwatch support was dropped and does not exist anymore.")
                ->end()
                ->variableNode('sentry')
                    ->info("Deprecated, sentry support was dropped and does not exist anymore.")
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
