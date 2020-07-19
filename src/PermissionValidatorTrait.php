<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\PbjxEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait PermissionValidatorTrait
{
    protected RequestStack $requestStack;

    public static function getSubscribedEvents()
    {
        return [
            'gdbots:pbjx:mixin:command.validate' => 'validate',
            'gdbots:pbjx:mixin:event.validate'   => 'validate',
            'gdbots:pbjx:mixin:request.validate' => 'validate',
        ];
    }

    public function validate(PbjxEvent $pbjxEvent): void
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

    protected function checkPermission(PbjxEvent $pbjxEvent, Message $message, Request $request): void
    {
        // override to provide custom validation logic.
    }
}
