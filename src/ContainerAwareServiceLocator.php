<?php

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Pbj\MessageCurie;
use Gdbots\Pbjx\AbstractServiceLocator;
use Gdbots\Pbjx\CommandBus;
use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\DefaultCommandBus;
use Gdbots\Pbjx\DefaultEventBus;
use Gdbots\Pbjx\DefaultRequestBus;
use Gdbots\Pbjx\EventBus;
use Gdbots\Pbjx\ExceptionHandler;
use Gdbots\Pbjx\Exception\HandlerNotFound;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RequestBus;
use Gdbots\Pbjx\RequestHandler;
use Gdbots\Pbjx\Transport;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ContainerAwareServiceLocator extends AbstractServiceLocator
{
    /** @var ContainerInterface */
    private $container;

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
     * {@inheritdoc}
     */
    public function getCommandHandler(MessageCurie $curie)
    {
        return $this->getHandler($curie);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestHandler(MessageCurie $curie)
    {
        return $this->getHandler($curie);
    }

    /**
     * @param MessageCurie $curie
     * @return CommandHandler|RequestHandler
     * @throws HandlerNotFound
     */
    protected function getHandler(MessageCurie $curie)
    {
        $id = $this->curieToServiceId($curie);

        try {
            return $this->container->get($id);
        } catch (\Exception $e) {
            throw new HandlerNotFound($curie, $e);
        }
    }

    /**
     * @param MessageCurie $curie
     * @return string
     */
    protected function curieToServiceId(MessageCurie $curie)
    {
        return str_replace('-', '_', sprintf('%s_%s.%s.%s_handler',
            $curie->getVendor(), $curie->getPackage(), $curie->getCategory(), $curie->getMessage())
        );
    }

    /**
     * @param string $name name of the bus, one of command, event or request
     * @return Transport
     * @throws \Exception
     */
    protected function getTransportForBus($name)
    {
        $transport = $this->container->getParameter('gdbots_pbjx.' . $name . '_bus.transport');
        if (empty($transport)) {
            return $this->getDefaultTransport();
        }
        return $this->container->get('gdbots_pbjx.transport.' . $transport);
    }
}
