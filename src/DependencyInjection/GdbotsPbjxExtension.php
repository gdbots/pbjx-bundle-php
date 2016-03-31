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

            switch ($transport) {
                case 'in_memory':
                    $this->validateInMemoryTransport($container);
                    break;

                case 'gearman':
                    $this->validateGearmanTransport($container);
                    break;

                case 'kinesis':
                    $this->validateKinesisTransport($container);
                    break;
            }

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

    /**
     * @param ContainerBuilder $container
     */
    private function validateInMemoryTransport(ContainerBuilder $container)
    {
        // nothing to check
    }

    /**
     * @param ContainerBuilder $container
     *
     * @throws \LogicException
     */
    private function validateGearmanTransport(ContainerBuilder $container)
    {
        $parameter = 'gdbots_pbjx.transport.gearman.servers';
        if (!$container->hasParameter($parameter)) {
            throw new \LogicException(sprintf(
                'The service "gdbots_pbjx.transport.gearman" has a dependency on a non-existent parameter "%s".',
                $parameter
            ));
        }

        $servers = $container->getParameter($parameter);
        if (empty($servers)) {
            throw new \LogicException(sprintf(
                'The service "gdbots_pbjx.transport.gearman" requires "%s" parameter to have at least 1 server configured.',
                $parameter
            ));
        }
    }

    /**
     * @param ContainerBuilder $container
     *
     * @throws \LogicException
     */
    private function validateKinesisTransport(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('gdbots_pbjx.transport.kinesis_router')) {
            throw new \LogicException('The service "gdbots_pbjx.transport.kinesis" has a dependency on a non-existent service "gdbots_pbjx.transport.kinesis_router".');
        }
    }
}
