<?php

namespace Gdbots\Bundle\PbjxBundle\Validator;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request as PbjxRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait PermissionValidatorTrait
{
    /** @var RequestStack */
    protected $requestStack;

    /**
     * @param PbjxEvent $pbjxEvent
     */
    public function validate(PbjxEvent $pbjxEvent)
    {
        if (!$pbjxEvent->isRootEvent()) {
            // lifecycle events on nested messages do not require permission
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            throw new AccessDeniedHttpException(sprintf(
                'A request is required to process [%s] messages.',
                $pbjxEvent->getMessage()->schema()->getId()->toString()
            ));
        }

        if ($request->attributes->getBoolean('pbjx_console')) {
            return;
        }

        $message = $pbjxEvent->getMessage();

        if (!$message instanceof Command && !$message instanceof Event && !$message instanceof PbjxRequest) {
            /*
             * all operations through pbjx that can change or reveal data will be one of the these types.
             * so if it's not this it's something like an Envelope or value object, etc and those don't
             * require permission as they can't be dealt with directly through pbjx.
             */
            return;
        }

        if ($message->has('ctx_causator_ref')) {
            /*
             * if the "ctx_causator_ref" is present it was populated by
             * the server and means this message is a sub request which
             * doesn't require a permission check.
             */
            return;
        }

        $this->checkPermission($pbjxEvent, $message, $request);
    }

    /**
     * @param PbjxEvent $pbjxEvent
     * @param Message $message
     * @param Request $request
     *
     * @throws \Exception
     */
    protected function checkPermission(PbjxEvent $pbjxEvent, Message $message, Request $request)
    {
        // override to provide custom validation logic.
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'gdbots_pbjx.message.validate' => 'validate',
        ];
    }
}
