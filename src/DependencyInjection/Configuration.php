<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\DependencyInjection;

use Gdbots\Bundle\PbjxBundle\ContainerAwareServiceLocator;
use Gdbots\Pbjx\EventSearch\Elastica\ElasticaEventSearch;
use Gdbots\Pbjx\EventSearch\Elastica\IndexManager;
use Gdbots\Pbjx\EventStore\DynamoDb\DynamoDbEventStore;
use Gdbots\Pbjx\EventStore\DynamoDb\EventStoreTable;
use Gdbots\Pbjx\Scheduler\DynamoDb\DynamoDbScheduler;
use Gdbots\Pbjx\Scheduler\DynamoDb\SchedulerTable;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('gdbots_pbjx');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('service_locator')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class')
                            ->defaultValue(ContainerAwareServiceLocator::class)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('pbjx_token_signer')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('default_kid')->defaultNull()->end()
                        ->arrayNode('keys')
                            ->prototype('array')
                                ->performNoDeepMerging()
                                ->children()
                                    ->scalarNode('kid')->isRequired()->end()
                                    ->scalarNode('secret')->isRequired()->end()
                                ->end()
                            ->end()
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
                        ->arrayNode('bypass_token_validation')
                            ->treatNullLike(['gdbots:pbjx:request:echo-request'])
                            ->defaultValue(['gdbots:pbjx:request:echo-request'])
                            ->scalarPrototype()->end()
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
                    ->end()
                ->end()
                ->arrayNode('transport')
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
                ->arrayNode('scheduler')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('provider')
                            ->defaultNull()
                        ->end()
                        ->append($this->getDynamoDbSchedulerConfigTree())
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    protected function getDynamoDbEventStoreConfigTree(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('dynamodb');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('class')
                    ->defaultValue(DynamoDbEventStore::class)
                ->end()
                ->scalarNode('table_name')
                    ->defaultValue('%env(default:app_vendor:APP_VENDOR)%-%env(default:app_env:CLOUD_ENV)%-event-store-'.EventStoreTable::SCHEMA_VERSION)
                ->end()
            ->end()
        ;

        return $node;
    }

    protected function getElasticaEventSearchConfigTree(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('elastica');
        $node = $treeBuilder->getRootNode();

        $defaultServers = [['host' => '127.0.0.1', 'port' => 9200]];
        $defaultCluster = [
            'default' => [
                'round_robin' => true,
                'timeout'     => 300,
                'debug'       => false,
                'persistent'  => true,
                'ssl'         => true,
                'servers'     => $defaultServers,
            ],
        ];

        $node
            ->addDefaultsIfNotSet()
            ->fixXmlConfig('cluster')
            ->children()
                ->scalarNode('class')
                    ->defaultValue(ElasticaEventSearch::class)
                ->end()
                ->arrayNode('index_manager')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class')
                            ->defaultValue(IndexManager::class)
                        ->end()
                        ->scalarNode('index_prefix')
                            ->defaultValue('%env(default:app_vendor:APP_VENDOR)%-%env(default:app_env:CLOUD_ENV)%-events')
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
                            ->booleanNode('ssl')
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

    protected function getDynamoDbSchedulerConfigTree(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('dynamodb');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('class')
                    ->defaultValue(DynamoDbScheduler::class)
                ->end()
                ->scalarNode('table_name')
                    ->defaultValue('%env(default:app_vendor:APP_VENDOR)%-%env(default:app_env:CLOUD_ENV)%-scheduler-'.SchedulerTable::SCHEMA_VERSION)
                ->end()
                ->scalarNode('state_machine_arn')
                    ->isRequired()
                ->end()
            ->end()
        ;

        return $node;
    }
}
