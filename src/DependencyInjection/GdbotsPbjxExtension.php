<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\DependencyInjection;

use Gdbots\Bundle\PbjxBundle\PbjxTokenSigner;
use Gdbots\Pbjx\DependencyInjection\PbjxBinder;
use Gdbots\Pbjx\DependencyInjection\PbjxEnricher;
use Gdbots\Pbjx\DependencyInjection\PbjxHandler;
use Gdbots\Pbjx\DependencyInjection\PbjxProjector;
use Gdbots\Pbjx\DependencyInjection\PbjxValidator;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Pbjx\Pbjx;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class GdbotsPbjxExtension extends Extension
{
    public function load(array $config, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $config);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('event_search.xml');
        $loader->load('event_store.xml');
        $loader->load('http.xml');
        $loader->load('services.xml');
        $loader->load('scheduler.xml');
        $loader->load('transport.xml');
        $loader->load('twig.xml');

        $container->setParameter('gdbots_pbjx.service_locator.class', $config['service_locator']['class']);

        $container->setParameter('gdbots_pbjx.pbjx_token_signer.default_kid', $config['pbjx_token_signer']['default_kid']);
        $container->setParameter('gdbots_pbjx.pbjx_token_signer.keys', $config['pbjx_token_signer']['keys']);

        $container->setParameter('gdbots_pbjx.pbjx_controller.allow_get_request', $config['pbjx_controller']['allow_get_request']);
        $container->setParameter('gdbots_pbjx.pbjx_controller.bypass_token_validation', $config['pbjx_controller']['bypass_token_validation']);

        $container->setParameter('gdbots_pbjx.pbjx_receive_controller.enabled', $config['pbjx_receive_controller']['enabled']);

        $container->setParameter('gdbots_pbjx.command_bus.transport', $config['command_bus']['transport']);
        $container->setParameter('gdbots_pbjx.event_bus.transport', $config['event_bus']['transport']);
        $container->setParameter('gdbots_pbjx.request_bus.transport', $config['request_bus']['transport']);
        $enabledTransports = array_flip([
            $config['command_bus']['transport'],
            $config['event_bus']['transport'],
            $config['request_bus']['transport'],
        ]);

        $this->configureKinesisTransport($config, $container, $enabledTransports);

        if (isset($config['event_store'])) {
            $container->setParameter('gdbots_pbjx.event_store.provider', $config['event_store']['provider']);
            $this->configureDynamoDbEventStore($config, $container, $config['event_store']['provider']);
        }

        if (isset($config['event_search'])) {
            $container->setParameter('gdbots_pbjx.event_search.provider', $config['event_search']['provider']);
            $this->configureElasticaEventSearch($config, $container, $config['event_search']['provider']);
        } else {
            $container->removeDefinition('gdbots_pbjx.event_search.event_indexer');
        }

        if (isset($config['scheduler'])) {
            $container->setParameter('gdbots_pbjx.scheduler.provider', $config['scheduler']['provider']);
            $this->configureDynamoDbScheduler($config, $container, $config['scheduler']['provider']);
        }

        $container->setAlias(Pbjx::class, 'pbjx');
        $container->setAlias(PbjxTokenSigner::class, 'gdbots_pbjx.pbjx_token_signer');

        $container->registerForAutoconfiguration(EventSubscriber::class)->addTag('pbjx.event_subscriber');
        $container->registerForAutoconfiguration(PbjxBinder::class)->addTag('pbjx.binder');
        $container->registerForAutoconfiguration(PbjxValidator::class)->addTag('pbjx.validator');
        $container->registerForAutoconfiguration(PbjxEnricher::class)->addTag('pbjx.enricher');
        $container->registerForAutoconfiguration(PbjxHandler::class)->addTag('pbjx.handler');
        $container->registerForAutoconfiguration(PbjxProjector::class)->addTag('pbjx.projector');
    }

    protected function configureKinesisTransport(array $config, ContainerBuilder $container, array $enabledTransports): void
    {
        if (!isset($enabledTransports['kinesis'])) {
            $container->removeDefinition('gdbots_pbjx.transport.kinesis');
            $container->removeDefinition('gdbots_pbjx.transport.kinesis_router');
            return;
        }
    }

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

    protected function configureDynamoDbScheduler(array $config, ContainerBuilder $container, ?string $provider): void
    {
        if (!isset($config['scheduler']['dynamodb']) || 'dynamodb' !== $provider) {
            $container->removeDefinition('gdbots_pbjx.scheduler.dynamodb');
            return;
        }

        $container->setParameter('gdbots_pbjx.scheduler.dynamodb.class', $config['scheduler']['dynamodb']['class']);
        $container->setParameter('gdbots_pbjx.scheduler.dynamodb.table_name', $config['scheduler']['dynamodb']['table_name']);
        $container->setParameter('gdbots_pbjx.scheduler.dynamodb.state_machine_arn', $config['scheduler']['dynamodb']['state_machine_arn']);
    }
}
