<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Binder;

use Gdbots\Pbj\Field;
use Gdbots\Pbjx\DependencyInjection\PbjxBinder;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request as PbjxRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class RequestBinder implements EventSubscriber, PbjxBinder
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
    public function bind(PbjxEvent $pbjxEvent): void
    {
        /** @var PbjxRequest $message */
        $message = $pbjxEvent->getMessage();
        $request = $this->getCurrentRequest();

        $restricted = !$request->attributes->getBoolean('pbjx_bind_unrestricted');
        $input = (array)$request->attributes->get('pbjx_input');

        if ($restricted) {
            $fields = array_filter(
                $message::schema()->getMixin('gdbots:pbjx:mixin:request')->getFields(),
                function (Field $field) {
                    $name = $field->getName();
                    return 'ctx_app' !== $name
                        && 'derefs' !== $name;
                }
            );

            $this->restrictBindFromInput($pbjxEvent, $message, $fields, $input);
        }

        $this->bindApp($pbjxEvent, $message, $request);
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
            'gdbots:pbjx:mixin:request.bind' => ['bind', 10000],
        ];
    }
}
