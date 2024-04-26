<?php

declare(strict_types=1);

namespace MakinaCorpus\Profiling\Bridge\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

final class ProfilingConfiguration implements ConfigurationInterface
{
    #[\Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('profiling');
        $rootNode = $treeBuilder->getRootNode();

        $allowedPrometheusStorage = ['query_builder', 'in_memory', 'apcu', 'apcng', 'redis'];

        // @phpstan-ignore-next-line
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
                ->arrayNode('prometheus')
                    ->children()
                    ->booleanNode('enabled')
                        ->info("Enable prometheus data collection and scrapping.")
                        ->defaultTrue()
                    ->end()
                    ->scalarNode('namespace')->defaultValue('symfony')->end()
                        ->variableNode('request_ignore_methods')->defaultValue(['OPTION'])->end()
                        ->variableNode('request_ignore_routes')->defaultValue([])->end()
                        ->variableNode('console_ignore')->defaultValue([])->end()
                        ->arrayNode('storage')
                            ->children()
                                ->enumNode('type')
                                    ->values($allowedPrometheusStorage)
                                    ->defaultValue('in_memory')
                                ->end()
                                ->scalarNode('uri')->defaultValue(null)->end()
                                ->variableNode('options')->end()
                            ->end()
                        ->end()
                        ->variableNode('schema')
                            // @todo Schema schema validation (lol).
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
