<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Checks the container to ensure that the scheduler has the provider defined
 * and that it's valid.
 */
final class ValidateSchedulerPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('gdbots_pbjx.scheduler.provider')) {
            return;
        }

        $provider = $container->getParameter('gdbots_pbjx.scheduler.provider');
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
     * @param string           $provider
     *
     * @throws \LogicException
     */
    private function ensureProviderExists(ContainerBuilder $container, string $provider): void
    {
        $serviceId = 'gdbots_pbjx.scheduler.' . $provider;
        if ($container->hasDefinition($serviceId)) {
            return;
        }

        throw new \LogicException(
            sprintf(
                'The "gdbots_pbjx.scheduler.provider" is configured to use "%s" which requires service "%s".',
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
    private function validateDynamoDbProvider(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('aws.dynamodb')) {
            throw new \LogicException(
                'The service "gdbots_pbjx.scheduler.dynamodb" has a dependency on a non-existent ' .
                'service "aws.dynamodb". This expects the DynamoDb Client that comes from ' .
                'composer package "aws/aws-sdk-php-symfony": "^1.0".'
            );
        }
    }
}
