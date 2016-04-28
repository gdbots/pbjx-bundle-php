<?php

namespace Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Checks the container to ensure that transports that have been configured
 * for the buses are actually defined and have their dependencies defined.
 *
 */
class ValidateTransportsPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        foreach (['command', 'event', 'request'] as $busName) {
            $transport = $container->getParameter('gdbots_pbjx.'.$busName.'_bus.transport');
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
            sprintf(
                'The "gdbots_pbjx.%s_bus.transport" is configured to use "%s" which requires service "%s".',
                $busName,
                $transport,
                $serviceId
            )
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
                'The service "gdbots_pbjx.transport.gearman" requires "%s" parameter to have at least 1 element(s) defined.',
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
            throw new \LogicException(
                'The service "gdbots_pbjx.transport.kinesis" has a dependency on a non-existent ' .
                'service "gdbots_pbjx.transport.kinesis_router". You must define this in your app ' .
                'since it requires stream names and partition logic. See \Gdbots\Pbjx\PartitionableRouter.'
            );
        }

        if (!$container->hasDefinition('aws.kinesis')) {
            throw new \LogicException(
                'The service "gdbots_pbjx.transport.kinesis" has a dependency on a non-existent ' .
                'service "aws.kinesis". This expects the Kinesis Client that comes from ' .
                'composer package "aws/aws-sdk-php-symfony": "~1.0".'
            );
        }
    }
}
