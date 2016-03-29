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
}
