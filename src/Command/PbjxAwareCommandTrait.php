<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Bundle\PbjxBundle\ContainerAwareServiceLocator;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\ServiceLocator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

trait PbjxAwareCommandTrait
{
    protected ContainerInterface $container;

    protected function getRequestStack(): RequestStack
    {
        return $this->container->get('request_stack');
    }

    protected function getPbjx(): Pbjx
    {
        return $this->container->get('pbjx');
    }

    protected function getPbjxServiceLocator(): ServiceLocator
    {
        return $this->container->get('gdbots_pbjx.service_locator');
    }

    /**
     * Some pbjx binders, validators, etc. expect a request to exist.
     * Create one if nothing has been created yet.
     */
    protected function createConsoleRequest(): Request
    {
        $requestStack = $this->getRequestStack();
        $request = $requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            $request = new Request();
            $requestStack->push($request);
        }

        $request->attributes->set('pbjx_console', true);
        $request->attributes->set('pbjx_bind_unrestricted', true);

        return $request;
    }

    protected function useInMemoryTransports(InputInterface $input, ?SymfonyStyle $io = null): void
    {
        if (!$input->hasOption('in-memory') || $input->getOption('in-memory')) {
            $locator = $this->getPbjxServiceLocator();
            if ($locator instanceof ContainerAwareServiceLocator) {
                $locator->forceTransportsToInMemory();
                if ($io) {
                    $io->note('Using in_memory transports.');
                }
            }
        }
    }

    protected function readyForPbjxTraffic(SymfonyStyle $io, string $message = 'Aborting replay of events.'): bool
    {
        $question = sprintf(
            'Have you prepared your event store [%s], transports [%s,%s] and your devops team for the added traffic? ',
            $this->container->getParameter('gdbots_pbjx.event_store.provider'),
            $this->container->getParameter('gdbots_pbjx.command_bus.transport'),
            $this->container->getParameter('gdbots_pbjx.event_bus.transport')
        );

        if (!$io->confirm($question)) {
            $io->note($message);
            return false;
        }

        return true;
    }
}
