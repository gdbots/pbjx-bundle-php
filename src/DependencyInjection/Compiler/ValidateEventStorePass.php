<?php

namespace Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Checks the container to ensure that the event store has it's provider defined.
 *
 */
class ValidateEventStorePass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('gdbots_pbjx.event_store')) {
            return;
        }

        $provider = $container->getParameter('gdbots_pbjx.event_store');
        if (empty($provider)) {
            return;
        }

        $this->ensureProviderExists($container, $provider);
        switch ($provider) {
            case 'dynamodb':
                $this->validateDynamoDbProvider($container);
                break;
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param string $provider
     *
     * @throws \LogicException
     */
    private function ensureProviderExists(ContainerBuilder $container, $provider)
    {
        $serviceId = 'gdbots_pbjx.event_store.'.$provider;
        if ($container->hasDefinition($serviceId)) {
            return;
        }

        throw new \LogicException(
            sprintf(
                'The "gdbots_pbjx.event_store" is configured to use "%s" which requires service "%s".',
                $provider,
                $serviceId
            )
        );
    }

    /**
     * @param ContainerBuilder $container
     *
     * @throws \LogicException
     */
    private function validateDynamoDbProvider(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('aws.dynamodb')) {
            throw new \LogicException(
                'The service "gdbots_pbjx.event_store.dynamodb" has a dependency on a non-existent ' .
                'service "aws.dynamodb". This expects the DynamoDb Client that comes from ' .
                'composer package "aws/aws-sdk-php-symfony": "~1.0".'
            );
        }
    }
}
