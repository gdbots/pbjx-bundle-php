<?php

namespace Gdbots\Bundle\PbjxBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class GdbotsPbjxExtension extends Extension
{
    /**
     * @param array $config
     * @param ContainerBuilder $container
     */
    public function load(array $config, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $config);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $container->setParameter('gdbots_pbjx.pbjx_controller.allow_get_request', $config['pbjx_controller']['allow_get_request']);

        if (isset($config['transport']['gearman'])) {
            $container->setParameter('gdbots_pbjx.transport.gearman.timeout', $config['transport']['gearman']['timeout']);
            $container->setParameter('gdbots_pbjx.transport.gearman.servers', $config['transport']['gearman']['servers']);
            $container->setParameter('gdbots_pbjx.transport.gearman.channel_prefix', $config['transport']['gearman']['channel_prefix']);
        }

        $container->setParameter('gdbots_pbjx.command_bus.transport', $config['command_bus']['transport']);
        $container->setParameter('gdbots_pbjx.event_bus.transport', $config['event_bus']['transport']);
        $container->setParameter('gdbots_pbjx.request_bus.transport', $config['request_bus']['transport']);
    }
}
