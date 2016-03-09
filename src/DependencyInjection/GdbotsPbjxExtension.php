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

        // update parameters with config
        $container->setParameter('gdbots_pbjx.pbjx_controller.allow_get_request', $config['pbjx_controller']['allow_get_request']);
        $container->setParameter('gdbots_pbjx.transport.gearman.timeout', $config['transport']['gearman']['timeout']);
        $container->setParameter('gdbots_pbjx.transport.gearman.servers', $config['transport']['gearman']['servers']);
        $container->setParameter('gdbots_pbjx.transport.gearman.channel_prefix', $config['transport']['gearman']['channel_prefix']);

        $this->addTransports($config, $container);
    }

    /**
     * @param array $config
     * @param ContainerBuilder $container
     */
    private function addTransports(array $config, ContainerBuilder $container)
    {
        foreach (['command', 'event', 'request'] as $busName) {
            $transport = $config[$busName.'_bus']['transport'];
            $this->ensureTransportExists($container, $busName, $transport);
            $container->setParameter('gdbots_pbjx.'.$busName.'_bus.transport', $transport);
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param string $busName
     * @param string $transport
     *
     * @throws \LogicException
     */
    private function ensureTransportExists(ContainerBuilder $container, $busName, $transport)
    {
        $serviceId = 'gdbots_pbjx.transport.'.$transport;
        if ($container->hasDefinition($serviceId)) {
            return;
        }

        throw new \LogicException(
            sprintf('The "%s_bus" transport "%s" requires service "%s".', $busName, $transport, $serviceId)
        );
    }
}
