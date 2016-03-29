<?php

namespace Gdbots\Bundle\PbjxBundle\Binder;

use Gdbots\Pbj\Field;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Schemas\Pbjx\Request\Request as PbjxRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RequestBinder implements EventSubscriber
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
        /** @var PbjxRequest $pbjxRequest */
        $pbjxRequest = $pbjxEvent->getMessage();
        $request = $this->getCurrentRequest();

        $restricted = !$request->attributes->getBoolean('pbjx_bind_unrestricted');
        $input = (array)$request->attributes->get('pbjx_input');

        if ($restricted) {
            $fields = array_filter(
                $pbjxRequest::schema()->getMixin('gdbots:pbjx:mixin:request')->getFields(),
                function(Field $field) {
                    // we allow the client to set ctx_app
                    return 'ctx_app' !== $field->getName();
                }
            );

            $this->restrictBindFromInput($pbjxRequest, $fields, $input);
        }

        $this->bindConsoleApp($pbjxRequest, $request);
        $this->bindCloud($pbjxRequest);
        $this->bindIp($pbjxRequest, $request);
        $this->bindUserAgent($pbjxRequest, $request);
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'gdbots:pbjx:mixin:request.bind' => [['bind', 10000]],
        ];
    }
}
