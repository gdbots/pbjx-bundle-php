<?php

namespace Gdbots\Bundle\PbjxBundle\Binder;

use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Schemas\Pbjx\Command\Command;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class CommandBinder implements EventSubscriber
{
    use MessageBinderTrait;

    /**
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @param PbjxEvent $pbjxEvent
     */
    public function bind(PbjxEvent $pbjxEvent)
    {
        /** @var Command $command */
        $command = $pbjxEvent->getMessage();
        $request = $this->requestStack->getCurrentRequest() ?: new Request();

        $restricted = !$request->attributes->getBoolean('pbjx_bind_unrestricted');
        $input = (array) $request->attributes->get('pbjx_input');

        if ($restricted) {
            $this->restrictBindFromInput(
                $command, $command::schema()->getMixin('gdbots:pbjx:mixin:command')->getFields(), $input
            );
        }
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
