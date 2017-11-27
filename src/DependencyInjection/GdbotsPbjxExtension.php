<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class GdbotsPbjxExtension extends Extension
{
    /**
     * @param array            $config
     * @param ContainerBuilder $container
     */
    public function load(array $config, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = new Configuration($container->getParameter('kernel.environment'));
        $config = $processor->processConfiguration($configuration, $config);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('event_search.xml');
        $loader->load('event_store.xml');
        $loader->load('http.xml');
        $loader->load('services.xml');
        $loader->load('transport.xml');
        $loader->load('twig.xml');

        $container->setParameter('gdbots_pbjx.service_locator.class', $config['service_locator']['class']);

        $container->setParameter('gdbots_pbjx.pbjx_controller.allow_get_request', $config['pbjx_controller']['allow_get_request']);

        $container->setParameter('gdbots_pbjx.pbjx_receive_controller.enabled', $config['pbjx_receive_controller']['enabled']);
        $container->setParameter('gdbots_pbjx.pbjx_receive_controller.receive_key', $config['pbjx_receive_controller']['receive_key']);

        $container->setParameter('gdbots_pbjx.handler_guesser.class', $config['handler_guesser']['class']);

        $container->setParameter('gdbots_pbjx.command_bus.transport', $config['command_bus']['transport']);
        $container->setParameter('gdbots_pbjx.event_bus.transport', $config['event_bus']['transport']);
        $container->setParameter('gdbots_pbjx.request_bus.transport', $config['request_bus']['transport']);
        $enabledTransports = array_flip([
            $config['command_bus']['transport'],
            $config['event_bus']['transport'],
            $config['request_bus']['transport'],
        ]);

        $this->configureGearmanTransport($config, $container, $enabledTransports);
        $this->configureKinesisTransport($config, $container, $enabledTransports);

        if (isset($config['event_store'])) {
            $container->setParameter('gdbots_pbjx.event_store.provider', $config['event_store']['provider']);
            $this->configureDynamoDbEventStore($config, $container, $config['event_store']['provider']);
        }

        if (isset($config['event_search'])) {
            $container->setParameter('gdbots_pbjx.event_search.provider', $config['event_search']['provider']);
            $container->setParameter('gdbots_pbjx.event_search.tenant_id_field', $config['event_search']['tenant_id_field']);
            $this->configureElasticaEventSearch($config, $container, $config['event_search']['provider']);
        } else {
            $container->removeDefinition('gdbots_pbjx.event_search.event_indexer');
        }
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     * @param array            $enabledTransports
     */
    protected function configureGearmanTransport(array $config, ContainerBuilder $container, array $enabledTransports): void
    {
        if (!isset($config['transport']['gearman']) || !isset($enabledTransports['gearman'])) {
            $container->removeDefinition('gdbots_pbjx.transport.gearman');
            $container->removeDefinition('gdbots_pbjx.transport.gearman_router');
            return;
        }

        $container->setParameter('gdbots_pbjx.transport.gearman.servers', $config['transport']['gearman']['servers']);
        $container->setParameter('gdbots_pbjx.transport.gearman.timeout', $config['transport']['gearman']['timeout']);
        $container->setParameter('gdbots_pbjx.transport.gearman.channel_prefix', $config['transport']['gearman']['channel_prefix']);
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     * @param array            $enabledTransports
     */
    protected function configureKinesisTransport(array $config, ContainerBuilder $container, array $enabledTransports): void
    {
        if (!isset($enabledTransports['kinesis'])) {
            $container->removeDefinition('gdbots_pbjx.transport.kinesis');
            $container->removeDefinition('gdbots_pbjx.transport.kinesis_router');
            return;
        }
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     * @param string           $provider
     */
    protected function configureDynamoDbEventStore(array $config, ContainerBuilder $container, ?string $provider): void
    {
        if (!isset($config['event_store']['dynamodb']) || 'dynamodb' !== $provider) {
            $container->removeDefinition('gdbots_pbjx.event_store.dynamodb');
            return;
        }

        $container->setParameter('gdbots_pbjx.event_store.dynamodb.class', $config['event_store']['dynamodb']['class']);
        if (isset($config['event_store']['dynamodb']['table_name'])) {
            $container->setParameter(
                'gdbots_pbjx.event_store.dynamodb.table_name',
                $config['event_store']['dynamodb']['table_name']
            );
        }
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     * @param string           $provider
     */
    protected function configureElasticaEventSearch(array $config, ContainerBuilder $container, ?string $provider): void
    {
        if (!isset($config['event_search']['elastica']) || 'elastica' !== $provider) {
            $container->removeDefinition('gdbots_pbjx.event_search.elastica');
            $container->removeDefinition('gdbots_pbjx.event_search.elastica.client_manager');
            $container->removeDefinition('gdbots_pbjx.event_search.elastica.index_manager');
            return;
        }

        $container->setParameter('gdbots_pbjx.event_search.elastica.class', $config['event_search']['elastica']['class']);
        $container->setParameter('gdbots_pbjx.event_search.elastica.index_manager.class', $config['event_search']['elastica']['index_manager']['class']);
        if (isset($config['event_search']['elastica']['index_manager']['index_prefix'])) {
            $container->setParameter(
                'gdbots_pbjx.event_search.elastica.index_manager.index_prefix',
                $config['event_search']['elastica']['index_manager']['index_prefix']
            );
        }
        $container->setParameter('gdbots_pbjx.event_search.elastica.query_timeout', $config['event_search']['elastica']['query_timeout']);
        $container->setParameter('gdbots_pbjx.event_search.elastica.clusters', $config['event_search']['elastica']['clusters']);
    }
}
