<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Validator;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Schemas\Pbjx\Mixin\Command\CommandV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Request\RequestV1Mixin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait PermissionValidatorTrait
{
    protected RequestStack $requestStack;

    public static function getSubscribedEvents()
    {
        return [
            CommandV1Mixin::SCHEMA_CURIE . '.validate' => 'validate',
            EventV1Mixin::SCHEMA_CURIE . '.validate'   => 'validate',
            RequestV1Mixin::SCHEMA_CURIE . '.validate' => 'validate',
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
        if ($message->has(CommandV1Mixin::CTX_CAUSATOR_REF_FIELD)) {
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
