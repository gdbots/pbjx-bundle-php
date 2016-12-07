<?php

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\AbstractServiceLocator;
use Gdbots\Pbjx\CommandBus;
use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\DefaultCommandBus;
use Gdbots\Pbjx\DefaultEventBus;
use Gdbots\Pbjx\DefaultRequestBus;
use Gdbots\Pbjx\EventBus;
use Gdbots\Pbjx\EventSearch\EventSearch;
use Gdbots\Pbjx\EventStore\EventStore;
use Gdbots\Pbjx\ExceptionHandler;
use Gdbots\Pbjx\Exception\HandlerNotFound;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RequestBus;
use Gdbots\Pbjx\RequestHandler;
use Gdbots\Pbjx\Transport\Transport;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ContainerAwareServiceLocator extends AbstractServiceLocator
{
    /** @var ContainerInterface */
    protected $container;

    /** @var HandlerGuesser */
    protected $handlerGuesser;

    /**
     * In some cases (console commands for example) we want to force
     * the system to use the in memory transports.
     *
     * @var bool
     */
    protected $forceTransportsToInMemory = false;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return Pbjx
     */
    protected function doGetPbjx()
    {
        return $this->container->get('pbjx');
    }

    /**
     * @return EventDispatcherInterface
     */
    protected function doGetDispatcher()
    {
        return $this->container->get('gdbots_pbjx.event_dispatcher');
    }

    /**
     * @return CommandBus
     */
    protected function doGetCommandBus()
    {
        return new DefaultCommandBus($this, $this->getTransportForBus('command'));
    }

    /**
     * @return EventBus
     */
    protected function doGetEventBus()
    {
        return new DefaultEventBus($this, $this->getTransportForBus('event'));
    }

    /**
     * @return RequestBus
     */
    protected function doGetRequestBus()
    {
        return new DefaultRequestBus($this, $this->getTransportForBus('request'));
    }

    /**
     * @return ExceptionHandler
     */
    protected function doGetExceptionHandler()
    {
        return $this->container->get('gdbots_pbjx.exception_handler');
    }

    /**
     * @return EventStore
     */
    protected function doGetEventStore()
    {
        $provider = $this->container->getParameter('gdbots_pbjx.event_store.provider');
        return $this->container->get('gdbots_pbjx.event_store.' . $provider);
    }

    /**
     * @return EventSearch
     */
    protected function doGetEventSearch()
    {
        $provider = $this->container->getParameter('gdbots_pbjx.event_search.provider');
        return $this->container->get('gdbots_pbjx.event_search.' . $provider);
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandHandler(SchemaCurie $curie)
    {
        return $this->getHandler($curie);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestHandler(SchemaCurie $curie)
    {
        return $this->getHandler($curie);
    }

    /**
     * Forces the transports to return the in memory version.
     * This is really only useful in cli commands where you
     * want to see the process run locally.
     */
    public function forceTransportsToInMemory()
    {
        $this->forceTransportsToInMemory = true;
    }

    /**
     * @param SchemaCurie $curie
     * @return CommandHandler|RequestHandler
     *
     * @throws HandlerNotFound
     */
    protected function getHandler(SchemaCurie $curie)
    {
        $id = $this->curieToServiceId($curie);

        try {
            return $this->container->get($id);
        } catch (\Exception $e) {
            $guesser = $this->getHandlerGuesser();
            /** @var CommandHandler|RequestHandler $className */
            $className = $guesser->guessHandler($curie);
            if (class_exists($className)) {
                return $guesser->createHandler($curie, $className, $this->container);
            }

            throw new HandlerNotFound($curie, $e);
        }
    }

    /**
     * @param SchemaCurie $curie
     * @return string
     */
    protected function curieToServiceId(SchemaCurie $curie)
    {
        return str_replace('-', '_', sprintf('%s_%s.%s_handler',
            $curie->getVendor(), $curie->getPackage(), $curie->getMessage())
        );
    }

    /**
     * @param string $name name of the bus, one of command, event or request
     *
     * @return Transport
     *
     * @throws \Exception
     */
    protected function getTransportForBus($name)
    {
        if ($this->forceTransportsToInMemory) {
            return $this->container->get('gdbots_pbjx.transport.in_memory');
        }

        $transport = $this->container->getParameter('gdbots_pbjx.' . $name . '_bus.transport');
        if (empty($transport)) {
            return $this->getDefaultTransport();
        }

        return $this->container->get('gdbots_pbjx.transport.' . $transport);
    }

    /**
     * @return HandlerGuesser
     */
    protected function getHandlerGuesser()
    {
        if (null === $this->handlerGuesser) {
            $this->handlerGuesser = $this->container->get('gdbots_pbjx.handler_guesser');
        }

        return $this->handlerGuesser;
    }
}
