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
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class GdbotsPbjxExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $processor = new Processor();
        $configuration = new Configuration();
        $configs = $processor->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('event_search.yml');
        $loader->load('event_store.yml');
        $loader->load('http.yml');
        $loader->load('services.yml');
        $loader->load('scheduler.yml');
        $loader->load('transport.yml');
        $loader->load('twig.yml');

        $container->setParameter('gdbots_pbjx.service_locator.class', $configs['service_locator']['class']);

        $container->setParameter('gdbots_pbjx.pbjx_token_signer.default_kid', $configs['pbjx_token_signer']['default_kid']);
        $container->setParameter('gdbots_pbjx.pbjx_token_signer.keys', $configs['pbjx_token_signer']['keys']);

        $container->setParameter('gdbots_pbjx.pbjx_controller.allow_get_request', $configs['pbjx_controller']['allow_get_request']);
        $container->setParameter('gdbots_pbjx.pbjx_controller.bypass_token_validation', $configs['pbjx_controller']['bypass_token_validation']);

        $container->setParameter('gdbots_pbjx.pbjx_receive_controller.enabled', $configs['pbjx_receive_controller']['enabled']);

        $container->setParameter('gdbots_pbjx.command_bus.transport', $configs['command_bus']['transport']);
        $container->setParameter('gdbots_pbjx.event_bus.transport', $configs['event_bus']['transport']);
        $container->setParameter('gdbots_pbjx.request_bus.transport', $configs['request_bus']['transport']);
        $enabledTransports = array_flip([
            $configs['command_bus']['transport'],
            $configs['event_bus']['transport'],
            $configs['request_bus']['transport'],
        ]);

        $this->configureKinesisTransport($configs, $container, $enabledTransports);

        if (isset($configs['event_store'])) {
            $container->setParameter('gdbots_pbjx.event_store.provider', $configs['event_store']['provider']);
            $this->configureDynamoDbEventStore($configs, $container, $configs['event_store']['provider']);
        }

        if (isset($configs['event_search'])) {
            $container->setParameter('gdbots_pbjx.event_search.provider', $configs['event_search']['provider']);
            $this->configureElasticaEventSearch($configs, $container, $configs['event_search']['provider']);
        } else {
            $container->removeDefinition('gdbots_pbjx.event_search.event_indexer');
        }

        if (isset($configs['scheduler'])) {
            $container->setParameter('gdbots_pbjx.scheduler.provider', $configs['scheduler']['provider']);
            $this->configureDynamoDbScheduler($configs, $container, $configs['scheduler']['provider']);
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

    protected function configureKinesisTransport(array $configs, ContainerBuilder $container, array $enabledTransports): void
    {
        if (!isset($enabledTransports['kinesis'])) {
            $container->removeDefinition('gdbots_pbjx.transport.kinesis');
            $container->removeDefinition('gdbots_pbjx.transport.kinesis_router');
            return;
        }
    }

    protected function configureDynamoDbEventStore(array $configs, ContainerBuilder $container, ?string $provider): void
    {
        if (!isset($configs['event_store']['dynamodb']) || 'dynamodb' !== $provider) {
            $container->removeDefinition('gdbots_pbjx.event_store.dynamodb');
            return;
        }

        $container->setParameter('gdbots_pbjx.event_store.dynamodb.class', $configs['event_store']['dynamodb']['class']);
        if (isset($configs['event_store']['dynamodb']['table_name'])) {
            $container->setParameter(
                'gdbots_pbjx.event_store.dynamodb.table_name',
                $configs['event_store']['dynamodb']['table_name']
            );
        }
    }

    protected function configureElasticaEventSearch(array $configs, ContainerBuilder $container, ?string $provider): void
    {
        if (!isset($configs['event_search']['elastica']) || 'elastica' !== $provider) {
            $container->removeDefinition('gdbots_pbjx.event_search.elastica');
            $container->removeDefinition('gdbots_pbjx.event_search.elastica.client_manager');
            $container->removeDefinition('gdbots_pbjx.event_search.elastica.index_manager');
            return;
        }

        $container->setParameter('gdbots_pbjx.event_search.elastica.class', $configs['event_search']['elastica']['class']);
        $container->setParameter('gdbots_pbjx.event_search.elastica.index_manager.class', $configs['event_search']['elastica']['index_manager']['class']);
        if (isset($configs['event_search']['elastica']['index_manager']['index_prefix'])) {
            $container->setParameter(
                'gdbots_pbjx.event_search.elastica.index_manager.index_prefix',
                $configs['event_search']['elastica']['index_manager']['index_prefix']
            );
        }
        $container->setParameter('gdbots_pbjx.event_search.elastica.query_timeout', $configs['event_search']['elastica']['query_timeout']);
        $container->setParameter('gdbots_pbjx.event_search.elastica.clusters', $configs['event_search']['elastica']['clusters']);
    }

    protected function configureDynamoDbScheduler(array $configs, ContainerBuilder $container, ?string $provider): void
    {
        if (!isset($configs['scheduler']['dynamodb']) || 'dynamodb' !== $provider) {
            $container->removeDefinition('gdbots_pbjx.scheduler.dynamodb');
            return;
        }

        $container->setParameter('gdbots_pbjx.scheduler.dynamodb.class', $configs['scheduler']['dynamodb']['class']);
        $container->setParameter('gdbots_pbjx.scheduler.dynamodb.table_name', $configs['scheduler']['dynamodb']['table_name']);
        $container->setParameter('gdbots_pbjx.scheduler.dynamodb.state_machine_arn', $configs['scheduler']['dynamodb']['state_machine_arn']);
    }
}
