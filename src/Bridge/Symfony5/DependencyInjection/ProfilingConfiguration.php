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
                            ->booleanNode('file_lock')->defaultFalse()->end()
                            ->scalarNode('file_permission')->defaultNull()->end()
                            // For "stream" type only.
                            ->scalarNode('path')->defaultNull()->end()
                            ->scalarNode('trigger')->defaultNull()->end()
                            ->scalarNode('type')->defaultNull()->end()
                            // When type is "service" only.
                            ->scalarNode('id')->defaultNull()->end()
                            // When type is "store" only.
                            ->scalarNode('store')->defaultNull()->end()
                            ->scalarNode('store_uri')->defaultNull()->end()
                            ->scalarNode('store_table')->defaultNull()->end()
                            // Will be ignored if handler doesn't support it.
                            ->arrayNode('threshold')
                                ->children()
                                    ->scalarNode('memory')->defaultNull()->end()
                                    ->scalarNode('time')->defaultNull()->end()
                                ->end()
                            ->end()
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
