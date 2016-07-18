<?php

namespace Gdbots\Bundle\PbjxBundle\Binder;

use Gdbots\Pbj\Field;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CommandBinder implements EventSubscriber
{
    use MessageBinderTrait;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param PbjxEvent $pbjxEvent
     */
    public function bind(PbjxEvent $pbjxEvent)
    {
        /** @var Command $message */
        $message = $pbjxEvent->getMessage();
        $request = $this->getCurrentRequest();

        $restricted = !$request->attributes->getBoolean('pbjx_bind_unrestricted');
        $input = (array)$request->attributes->get('pbjx_input');

        if ($restricted) {
            $fields = array_filter(
                $message::schema()->getMixin('gdbots:pbjx:mixin:command')->getFields(),
                function(Field $field) {
                    // we allow the client to set ctx_app
                    return 'ctx_app' !== $field->getName();
                }
            );

            $this->restrictBindFromInput($pbjxEvent, $message, $fields, $input);
        }

        $this->bindConsoleApp($pbjxEvent, $message, $request);
        $this->bindCloud($pbjxEvent, $message, $request);
        $this->bindIp($pbjxEvent, $message, $request);
        $this->bindUserAgent($pbjxEvent, $message, $request);
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'gdbots:pbjx:mixin:command.bind' => [['bind', 10000]],
        ];
    }
}
