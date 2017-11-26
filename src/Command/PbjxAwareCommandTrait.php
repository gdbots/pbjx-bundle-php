<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Bundle\PbjxBundle\ContainerAwareServiceLocator;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Mixin;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\SchemaQName;
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
trait PbjxAwareCommandTrait
{
    /**
     * @return RequestStack
     */
    protected function getRequestStack(): RequestStack
    {
        return $this->getContainer()->get('request_stack');
    }

    /**
     * @return Pbjx
     */
    protected function getPbjx(): Pbjx
    {
        return $this->getContainer()->get('pbjx');
    }

    /**
     * @return ServiceLocator
     */
    protected function getPbjxServiceLocator(): ServiceLocator
    {
        return $this->getContainer()->get('gdbots_pbjx.service_locator');
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger(): LoggerInterface
    {
        if ($this->getContainer()->has('monolog.logger.pbjx')) {
            return $this->getContainer()->get('monolog.logger.pbjx');
        }

        return $this->getContainer()->get('logger');
    }

    /**
     * Some pbjx binders, validators, etc. expect a request to exist.
     * Create one if nothing has been created yet.
     *
     * @return Request
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

    /**
     * Running transports "in-memory" means the command/request handlers and event
     * subscribers to pbjx messages will happen in this process and not run through
     * kinesis, gearman, sqs, etc.
     *
     * @param InputInterface $input
     * @param SymfonyStyle   $io
     */
    protected function useInMemoryTransports(InputInterface $input, ?SymfonyStyle $io = null): void
    {
        if ($input->getOption('in-memory')) {
            $locator = $this->getPbjxServiceLocator();
            if ($locator instanceof ContainerAwareServiceLocator) {
                $locator->forceTransportsToInMemory();
                if ($io) {
                    $io->note('Using in_memory transports.');
                }
            }
        }
    }

    /**
     * @param SymfonyStyle $io
     * @param string       $message
     *
     * @return bool
     */
    protected function readyForPbjxTraffic(SymfonyStyle $io, string $message = 'Aborting replay of events.'): bool
    {
        $container = $this->getContainer();
        $question = sprintf(
            'Have you prepared your event store [%s], transports [%s,%s] and your devops team for the added traffic? ',
            $container->getParameter('gdbots_pbjx.event_store.provider'),
            $container->getParameter('gdbots_pbjx.command_bus.transport'),
            $container->getParameter('gdbots_pbjx.event_bus.transport')
        );

        if (!$io->confirm($question)) {
            $io->note($message);
            return false;
        }

        return true;
    }

    /**
     * @param Mixin  $mixin
     * @param string $qname
     *
     * @return Schema[]
     */
    protected function getSchemasUsingMixin(Mixin $mixin, ?string $qname = null): array
    {
        $curie = $mixin->getId()->getCurieMajor();

        if (null === $qname) {
            $schemas = MessageResolver::findAllUsingMixin($mixin);
        } else {
            /** @var Message $class */
            $class = MessageResolver::resolveCurie(
                MessageResolver::resolveQName(SchemaQName::fromString($qname))
            );
            $schema = $class::schema();

            if (!$schema->hasMixin($curie)) {
                throw new \InvalidArgumentException(
                    sprintf('The SchemaQName [%s] does not have mixin [%s].', $qname, $curie)
                );
            }

            $schemas = [$schema];
        }

        return $schemas;
    }
}
