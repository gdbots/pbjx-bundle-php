<?php

namespace Gdbots\Bundle\MessagingBundle;

use Gdbots\Messaging\Exception\HandlerNotFoundException;
use Gdbots\Messaging\MessageCurie;
use Gdbots\Messaging\ServiceLocatorInterface;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class ContainerAwareServiceLocator extends ContainerAware implements ServiceLocatorInterface
{
    /**
     * @see ServiceLocatorInterface::getCommandBus
     */
    public function getCommandBus()
    {
        return $this->container->get('gdbots_messaging.command_bus');
    }

    /**
     * @see ServiceLocatorInterface::getEventBus
     */
    public function getEventBus()
    {
        return $this->container->get('gdbots_messaging.event_bus');
    }

    /**
     * @see ServiceLocatorInterface::getRequestBus
     */
    public function getRequestBus()
    {
        return $this->container->get('gdbots_messaging.request_bus');
    }

    /**
     * @see ServiceLocatorInterface::getCommandHandler
     */
    public function getCommandHandler(MessageCurie $curie)
    {
        return $this->getHandler($curie);
    }

    /**
     * @see ServiceLocatorInterface::getRequestHandler
     */
    public function getRequestHandler(MessageCurie $curie)
    {
        return $this->getHandler($curie);
    }

    /**
     * @param MessageCurie $curie
     * @return mixed
     * @throws HandlerNotFoundException
     */
    protected function getHandler(MessageCurie $curie)
    {
        $serviceId = $this->curieToServiceId($curie);
        try {
            return $this->container->get($serviceId);
        } catch (ServiceNotFoundException $e) {
            throw new HandlerNotFoundException(sprintf('Service with id [%s] for curie [%s] could not be found.', $serviceId, (string) $curie));
        }
    }

    /**
     * @param MessageCurie $curie
     * @return string
     */
    protected function curieToServiceId(MessageCurie $curie)
    {
        $type = $curie->getType() === $curie::COMMAND ? 'command' : 'request';
        return str_replace('-', '_', sprintf('%s_%s.%s_%s.handler',
                $curie->getNamespace(), $curie->getService(), $curie->getMessage(), $type)
            );
    }
}
