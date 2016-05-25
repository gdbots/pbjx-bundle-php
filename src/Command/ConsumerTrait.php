<?php

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Bundle\PbjxBundle\ContainerAwareServiceLocator;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\ServiceLocator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @method ContainerInterface getContainer()
 */
trait ConsumerTrait
{
    /**
     * @return Pbjx
     */
    protected function getPbjx()
    {
        return $this->getContainer()->get('pbjx');
    }

    /**
     * @return ServiceLocator
     */
    protected function getPbjxServiceLocator()
    {
        return $this->getContainer()->get('gdbots_pbjx.service_locator');
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        if ($this->getContainer()->has('monolog.logger.pbjx')) {
            return $this->getContainer()->get('monolog.logger.pbjx');
        }

        return $this->getContainer()->get('logger');
    }

    /**
     * Some pbjx binders, validators, etc. expect a request to exist.  Create one
     * if nothing has been created yet.
     */
    protected function createConsoleRequest()
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->getContainer()->get('request_stack');
        $request = $requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            $request = new Request();
            $requestStack->push($request);
        }

        $request->attributes->set('pbjx_console', true);
        $request->attributes->set('pbjx_bind_unrestricted', true);
    }

    /**
     * Running transports "in-memory" means the command/request handlers and event
     * subscribers to pbjx messages will happen in this process and not run through
     * kinesis, gearman, sqs, etc.  Generally used for debugging.
     *
     * @param InputInterface $input
     * @param SymfonyStyle $io
     */
    protected function useInMemoryTransports(InputInterface $input, SymfonyStyle $io)
    {
        if ($input->getOption('in-memory')) {
            $locator = $this->getContainer()->get('gdbots_pbjx.service_locator');
            if ($locator instanceof ContainerAwareServiceLocator) {
                $locator->forceTransportsToInMemory();
                $io->note('Using in_memory transports.');
            }
        }
    }

    /**
     * @param SymfonyStyle $io
     * @return bool
     */
    protected function readyForReplayTraffic(SymfonyStyle $io)
    {
        $question = sprintf(
            'Have you prepared your event store [%s] and your devops team for the added traffic? ',
            $this->getContainer()->getParameter('gdbots_pbjx.event_store.provider')
        );

        if (!$io->confirm($question)) {
            $io->note('Aborting replay of events.');
            return false;
        }

        return true;
    }
}
