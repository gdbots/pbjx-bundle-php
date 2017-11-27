<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\DependencyInjection;

use Gdbots\Pbjx\EventStore\DynamoDb\EventStoreTable;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    private $env;

    /**
     * @param string $env
     */
    public function __construct(string $env = 'dev')
    {
        $this->env = $env;
    }

    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('gdbots_pbjx');

        $rootNode
            ->children()
                ->arrayNode('service_locator')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class')
                            ->defaultValue('Gdbots\Bundle\PbjxBundle\ContainerAwareServiceLocator')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('pbjx_controller')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('allow_get_request')
                            ->defaultFalse()
                            ->treatNullLike(false)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('pbjx_receive_controller')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->treatNullLike(false)
                        ->end()
                        ->scalarNode('receive_key')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('handler_guesser')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class')
                            ->defaultValue('Gdbots\Bundle\PbjxBundle\HandlerGuesser')
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
                        ->scalarNode('tenant_id_field')
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
    protected function getGearmanTransportConfigTree(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('gearman');

        $node
            ->addDefaultsIfNotSet()
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
                    ->defaultValue("{$this->env}_")
                ->end()
            ->end()
        ;

        return $node;
    }

    /**
     * @return NodeDefinition
     */
    protected function getDynamoDbEventStoreConfigTree(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('dynamodb');

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('class')
                    ->defaultValue('Gdbots\Pbjx\EventStore\DynamoDb\DynamoDbEventStore')
                ->end()
                ->scalarNode('table_name')
                    ->defaultValue("{$this->env}-event-store-".EventStoreTable::SCHEMA_VERSION)
                ->end()
            ->end()
        ;

        return $node;
    }

    /**
     * @return NodeDefinition
     */
    protected function getElasticaEventSearchConfigTree(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('elastica');

        $defaultServers = [['host' => '127.0.0.1', 'port' => 9200]];
        $defaultCluster = [
            'default' => [
                'round_robin' => true,
                'timeout'     => 300,
                'debug'       => false,
                'persistent'  => true,
                'servers'     => $defaultServers,
            ],
        ];

        $node
            ->addDefaultsIfNotSet()
            ->fixXmlConfig('cluster')
            ->children()
                ->scalarNode('class')
                    ->defaultValue('Gdbots\Pbjx\EventSearch\Elastica\ElasticaEventSearch')
                ->end()
                ->arrayNode('index_manager')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class')
                            ->defaultValue('Gdbots\Pbjx\EventSearch\Elastica\IndexManager')
                        ->end()
                        ->scalarNode('index_prefix')
                            ->defaultValue("{$this->env}-events")
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('query_timeout')
                    ->defaultValue('500ms')
                    ->treatNullLike('500ms')
                ->end()
                ->arrayNode('clusters')
                    ->useAttributeAsKey('name')
                    ->treatNullLike($defaultCluster)
                    ->defaultValue($defaultCluster)
                    ->prototype('array')
                        ->fixXmlConfig('server')
                        ->addDefaultsIfNotSet()
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
