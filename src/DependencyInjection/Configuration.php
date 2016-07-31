<?php

namespace Gdbots\Bundle\PbjxBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
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
                        ->booleanNode('allow_get_request')
                            ->defaultFalse()
                            ->treatNullLike(false)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('transport')
                    ->children()
                        ->append($this->getGearmanTransportConfigTree())
                    ->end()
                ->end()
                ->arrayNode('command_bus')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('transport')
                            ->defaultValue('in_memory')
                            ->treatNullLike('in_memory')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('event_bus')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('transport')
                            ->defaultValue('in_memory')
                            ->treatNullLike('in_memory')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('request_bus')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('transport')
                            ->defaultValue('in_memory')
                            ->treatNullLike('in_memory')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('event_store')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('provider')
                            ->defaultNull()
                        ->end()
                        ->append($this->getDynamoDbEventStoreConfigTree())
                    ->end()
                ->end()
                ->arrayNode('event_search')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('provider')
                            ->defaultNull()
                        ->end()
                        ->append($this->getElasticaEventSearchConfigTree())
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
            ->children()
                ->arrayNode('servers')
                    ->requiresAtLeastOneElement()
                    //->isRequired()
                    ->treatNullLike([['host' => '127.0.0.1', 'port' => 4730]])
                    ->defaultValue([['host' => '127.0.0.1', 'port' => 4730]])
                    ->prototype('array')
                        ->performNoDeepMerging()
                        ->children()
                            ->scalarNode('host')
                                ->defaultValue('127.0.0.1')
                                ->treatNullLike('127.0.0.1')
                            ->end()
                            ->integerNode('port')
                                ->defaultValue(4730)
                                ->treatNullLike(4730)
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->integerNode('timeout')
                    ->defaultValue(5000)
                    ->treatNullLike(5000)
                ->end()
                ->scalarNode('channel_prefix')
                    ->defaultNull()
                ->end()
            ->end()
        ;

        return $node;
    }

    /**
     * @return NodeDefinition
     */
    protected function getDynamoDbEventStoreConfigTree()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('dynamodb');

        $node
            ->children()
                ->scalarNode('class')
                    ->defaultValue('Gdbots\Pbjx\EventStore\DynamoDbEventStore')
                ->end()
                ->scalarNode('table_name')->end()
            ->end()
        ;

        return $node;
    }

    /**
     * @return NodeDefinition
     */
    protected function getElasticaEventSearchConfigTree()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('elastica');

        $defaultServers = [['host' => '127.0.0.1', 'port' => 9200]];
        $defaultCluster = [
            'default' => [
                'round_robin' => true,
                'timeout' => 300,
                'debug' => false,
                'persistent' => true,
                'servers' => $defaultServers
            ]
        ];

        $node
            ->fixXmlConfig('cluster')
            ->children()
                ->scalarNode('class')
                    ->defaultValue('Gdbots\Pbjx\EventSearch\ElasticaEventSearch')
                ->end()
                ->arrayNode('index_manager')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class')
                            ->defaultValue('Gdbots\Pbjx\EventSearch\ElasticaIndexManager')
                        ->end()
                        ->scalarNode('index_prefix')->end()
                    ->end()
                ->end()
                ->scalarNode('query_timeout')
                    ->defaultValue('100ms')
                    ->treatNullLike('100ms')
                ->end()
                ->arrayNode('clusters')
                    ->useAttributeAsKey('name')
                    ->treatNullLike($defaultCluster)
                    ->defaultValue($defaultCluster)
                    ->prototype('array')
                        ->fixXmlConfig('server')
                        ->performNoDeepMerging()
                        ->children()
                            ->booleanNode('round_robin')
                                ->defaultTrue()
                                ->treatNullLike(true)
                            ->end()
                            ->integerNode('timeout')
                                ->info(
                                    'Number of seconds after a timeout occurs for every request. ' .
                                    'If using indexing of file large value necessary.'
                                )
                                ->defaultValue(300)
                                ->treatNullLike(300)
                            ->end()
                            ->booleanNode('debug')
                                ->defaultFalse()
                                ->treatNullLike(false)
                            ->end()
                            ->booleanNode('persistent')
                                ->defaultTrue()
                                ->treatNullLike(true)
                            ->end()
                            ->arrayNode('servers')
                                ->requiresAtLeastOneElement()
                                ->treatNullLike($defaultServers)
                                ->defaultValue($defaultServers)
                                ->prototype('array')
                                    ->performNoDeepMerging()
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('host')
                                            ->defaultValue('127.0.0.1')
                                            ->treatNullLike('127.0.0.1')
                                        ->end()
                                        ->integerNode('port')
                                            ->defaultValue(9200)
                                            ->treatNullLike(9200)
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }
}
