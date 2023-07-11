<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Checks the container to ensure that transports that have been configured
 * for the buses are actually defined and have their dependencies defined.
 */
final class ValidateTransportsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach (['command', 'event', 'request'] as $busName) {
            $transport = $container->getParameter('gdbots_pbjx.' . $busName . '_bus.transport');
            $this->ensureTransportExists($container, $busName, $transport);

            switch ($transport) {
                case 'in_memory':
                    $this->validateInMemoryTransport($container);
                    break;

                case 'firehose':
                    $this->validateFirehoseTransport($container);
                    break;

                case 'kinesis':
                    $this->validateKinesisTransport($container);
                    break;
            }
        }
    }

    private function ensureTransportExists(ContainerBuilder $container, string $busName, string $transport): void
    {
        $serviceId = 'gdbots_pbjx.transport.' . $transport;
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

    private function validateInMemoryTransport(ContainerBuilder $container): void
    {
        // nothing to check
    }

    private function validateFirehoseTransport(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('gdbots_pbjx.transport.firehose_router')) {
            throw new \LogicException(
                'The service "gdbots_pbjx.transport.firehose" has a dependency on a non-existent ' .
                'service "gdbots_pbjx.transport.firehose_router". You must define this in your app ' .
                'since it requires a delivery stream name which may be message specific.'
            );
        }

        if (!$container->hasDefinition('aws.firehose')) {
            throw new \LogicException(
                'The service "gdbots_pbjx.transport.firehose" has a dependency on a non-existent ' .
                'service "aws.firehose". This expects the Firehose Client that comes from ' .
                'composer package "aws/aws-sdk-php-symfony": "^1.0".'
            );
        }
    }

    private function validateKinesisTransport(ContainerBuilder $container): void
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
                'composer package "aws/aws-sdk-php-symfony": "^1.0".'
            );
        }
    }
}
