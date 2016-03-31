<?php

namespace Gdbots\Bundle\PbjxBundle\Validator;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\PbjxEvent;
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
        $schema = $message::schema();

        if ($schema->getCurie()->toString() !== $request->attributes->get('pbjx_curie')) {
            // this means the current message is a sub request and is okay to process.
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
            'eme:solicits:event:solicit-responded-to' => 'test',
        ];
    }
}