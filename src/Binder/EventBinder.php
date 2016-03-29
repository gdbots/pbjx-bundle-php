<?php

namespace Gdbots\Bundle\PbjxBundle\Binder;

use Gdbots\Pbj\Field;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Schemas\Pbjx\Event\Event;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EventBinder implements EventSubscriber
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
        /** @var Event $event */
        $event = $pbjxEvent->getMessage();
        $request = $this->getCurrentRequest();

        $restricted = !$request->attributes->getBoolean('pbjx_bind_unrestricted');
        $input = (array)$request->attributes->get('pbjx_input');

        if ($restricted) {
            $fields = array_filter(
                $event::schema()->getMixin('gdbots:pbjx:mixin:event')->getFields(),
                function(Field $field) {
                    // we allow the client to set ctx_app
                    return 'ctx_app' !== $field->getName();
                }
            );

            $this->restrictBindFromInput($event, $fields, $input);
        }

        $this->bindConsoleApp($event, $request);
        $this->bindIp($event, $request);
        $this->bindUserAgent($event, $request);
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'gdbots:pbjx:mixin:event.bind' => [['bind', 10000]],
        ];
    }
}
