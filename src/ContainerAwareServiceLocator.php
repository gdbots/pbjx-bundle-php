<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\AbstractServiceLocator;
use Gdbots\Pbjx\CommandBus;
use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\EventBus;
use Gdbots\Pbjx\EventSearch\EventSearch;
use Gdbots\Pbjx\EventStore\EventStore;
use Gdbots\Pbjx\Exception\HandlerNotFound;
use Gdbots\Pbjx\ExceptionHandler;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RequestBus;
use Gdbots\Pbjx\RequestHandler;
use Gdbots\Pbjx\SimpleCommandBus;
use Gdbots\Pbjx\SimpleEventBus;
use Gdbots\Pbjx\SimpleRequestBus;
use Gdbots\Pbjx\Transport\Transport;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ContainerAwareServiceLocator extends AbstractServiceLocator
{
    /** @var ContainerInterface */
    protected $container;

    /**
     * An array of handlers keyed by the curie.
     *
     * @var CommandHandler|RequestHandler[]
     */
    protected $handlers = [];

    /**
     * An array of functions keyed by the curie that
     * return a handler for the given curie.
     *
     * @var callable[]
     */
    protected $registeredHandlers = [];

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
    protected function doGetPbjx(): Pbjx
    {
        return $this->container->get('pbjx');
    }

    /**
     * @return EventDispatcherInterface
     */
    protected function doGetDispatcher(): EventDispatcherInterface
    {
        return $this->container->get('gdbots_pbjx.event_dispatcher');
    }

    /**
     * @return CommandBus
     */
    protected function doGetCommandBus(): CommandBus
    {
        return new SimpleCommandBus($this, $this->getTransportForBus('command'));
    }

    /**
     * @return EventBus
     */
    protected function doGetEventBus(): EventBus
    {
        return new SimpleEventBus($this, $this->getTransportForBus('event'));
    }

    /**
     * @return RequestBus
     */
    protected function doGetRequestBus(): RequestBus
    {
        return new SimpleRequestBus($this, $this->getTransportForBus('request'));
    }

    /**
     * @return ExceptionHandler
     */
    protected function doGetExceptionHandler(): ExceptionHandler
    {
        return $this->container->get('gdbots_pbjx.exception_handler');
    }

    /**
     * @return EventStore
     */
    protected function doGetEventStore(): EventStore
    {
        $provider = $this->container->getParameter('gdbots_pbjx.event_store.provider');
        return $this->container->get('gdbots_pbjx.event_store.' . $provider);
    }

    /**
     * @return EventSearch
     */
    protected function doGetEventSearch(): EventSearch
    {
        $provider = $this->container->getParameter('gdbots_pbjx.event_search.provider');
        return $this->container->get('gdbots_pbjx.event_search.' . $provider);
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandHandler(SchemaCurie $curie): CommandHandler
    {
        return $this->getHandler($curie);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestHandler(SchemaCurie $curie): RequestHandler
    {
        return $this->getHandler($curie);
    }

    /**
     * Forces the transports to return the in memory version.
     * This is really only useful in cli commands where you
     * want to see the process run locally.
     */
    public function forceTransportsToInMemory(): void
    {
        $this->forceTransportsToInMemory = true;
    }

    /**
     * @param SchemaCurie $curie
     *
     * @return CommandHandler|RequestHandler
     *
     * @throws HandlerNotFound
     */
    protected function getHandler(SchemaCurie $curie)
    {
        $key = $curie->toString();

        if (isset($this->handlers[$key])) {
            return $this->handlers[$key];
        }

        if (isset($this->registeredHandlers[$key])) {
            $this->handlers[$key] = $this->registeredHandlers[$key]();
            unset($this->registeredHandlers[$key]);
            return $this->handlers[$key];
        }

        throw new HandlerNotFound($curie);
    }

    /**
     * @see RegisterHandlersPass uses this method for all services tagged with "pbjx.handler"
     *
     * @internal
     *
     * @param string   $curie
     * @param callable $handler
     */
    public function registerHandler(string $curie, callable $handler): void
    {
        $this->registeredHandlers[$curie] = $handler;
    }

    /**
     * @param string $name name of the bus, one of command, event or request
     *
     * @return Transport
     *
     * @throws \Throwable
     */
    protected function getTransportForBus(string $name): Transport
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
}
