<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Checks the container to ensure that the event search has the provider defined
 * and that it's valid.
 */
final class ValidateEventSearchPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('gdbots_pbjx.event_search.provider')) {
            return;
        }

        $provider = $container->getParameter('gdbots_pbjx.event_search.provider');
        if (empty($provider)) {
            return;
        }

        $this->ensureProviderExists($container, $provider);

        switch ($provider) {
            case 'elastica':
                $this->validateElasticaProvider($container);
                break;
        }
    }

    private function ensureProviderExists(ContainerBuilder $container, $provider): void
    {
        $serviceId = 'gdbots_pbjx.event_search.' . $provider;
        if ($container->hasDefinition($serviceId)) {
            return;
        }

        throw new \LogicException(
            sprintf(
                'The "gdbots_pbjx.event_search.provider" is configured to use "%s" which requires service "%s".',
                $provider,
                $serviceId
            )
        );
    }

    private function validateElasticaProvider(ContainerBuilder $container): void
    {
        // validate here
    }
}
