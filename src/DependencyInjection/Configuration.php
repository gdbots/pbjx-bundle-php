<?php

namespace Gdbots\Bundle\PbjxBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('gdbots_pbjx');

        $rootNode
            ->children()
                ->arrayNode('pbjx_controller')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('allow_get_request')->defaultFalse()->treatNullLike(false)->end()
                    ->end()
                ->end()
                ->arrayNode('transport')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->append($this->getGearmanTransportConfigTree())
                    ->end()
                ->end()
                ->arrayNode('command_bus')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('transport')->defaultValue('in_memory')->treatNullLike('in_memory')->end()
                    ->end()
                ->end()
                ->arrayNode('event_bus')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('transport')->defaultValue('in_memory')->treatNullLike('in_memory')->end()
                    ->end()
                ->end()
                ->arrayNode('request_bus')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('transport')->defaultValue('in_memory')->treatNullLike('in_memory')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * @return NodeDefinition
     */
    protected function getGearmanTransportConfigTree()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('gearman');

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('servers')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('host')->defaultValue('127.0.0.1')->end()
                        ->scalarNode('port')->defaultValue('4730')->end()
                    ->end()
                ->end()
                ->integerNode('timeout')->defaultValue('5000')->end()
                ->scalarNode('channel_prefix')->defaultNull()->end()
            ->end()
        ;

        return $node;
    }
}
