<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\EventListener;

use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Enum\HttpCode;
use Gdbots\Schemas\Pbjx\EnvelopeV1;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * Handles the conversion of an Envelope to a symfony response.
 */
final class EnvelopeListener
{
    private Pbjx $pbjx;
    private LoggerInterface $logger;

    public function __construct(Pbjx $pbjx, ?LoggerInterface $logger = null)
    {
        $this->pbjx = $pbjx;
        $this->logger = $logger ?: new NullLogger();
    }

    public function onKernelView(ViewEvent $event): void
    {
        $envelope = $event->getControllerResult();
        if (!$envelope instanceof EnvelopeV1) {
            return;
        }

        $request = $event->getRequest();
        $request->setRequestFormat('json');
        $redact = $request->attributes->getBoolean('pbjx_redact_error_message', true);

        try {
            $this->pbjx->triggerLifecycle($envelope, false);
        } catch (\Throwable $e) {
            $this->logger->error('Error running pbjx->triggerLifecycle on envelope.', ['exception' => $e]);
        }

        $envelope->set(EnvelopeV1::OK_FIELD, Code::OK === $envelope->get(EnvelopeV1::CODE_FIELD));
        $httpCode = $envelope->has(EnvelopeV1::HTTP_CODE_FIELD)
            ? $envelope->get(EnvelopeV1::HTTP_CODE_FIELD)->getValue()
            : 200;
        $array = $envelope->toArray();

        if (isset($array[EnvelopeV1::ERROR_MESSAGE_FIELD]) && $redact) {
            if ($httpCode >= HttpCode::HTTP_INTERNAL_SERVER_ERROR) {
                $this->logger->error(
                    sprintf(
                        '%s::Message [{pbj_schema}] failed (Code:%s,HttpCode:%s).',
                        $envelope->get(EnvelopeV1::ERROR_NAME_FIELD),
                        $envelope->get(EnvelopeV1::CODE_FIELD),
                        $httpCode
                    ),
                    [
                        'pbj_schema' => $envelope::schema()->getId()->toString(),
                        'pbj'        => $array,
                    ]
                );
            }

            $array[EnvelopeV1::ERROR_MESSAGE_FIELD] = $this->redactErrorMessage($envelope, $request);
        }

        $response = new JsonResponse($array, $httpCode, [
            'Content-Type'       => 'application/json',
            'ETag'               => $envelope->get(EnvelopeV1::ETAG_FIELD),
            'x-pbjx-envelope-id' => (string)$envelope->get(EnvelopeV1::ENVELOPE_ID_FIELD),
        ]);

        if ($request->attributes->getBoolean('_jsonp_enabled') && $request->query->has('callback')) {
            // this may throw an exception but at this point someone
            // trying to break jsonp can get a malformed error.
            $response->setCallback($request->query->get('callback'));
        }

        $event->setResponse($response);
    }

    /**
     * It's generally not safe to render exception messages to the outside world.
     * So when we're pushing an error to the outside world (http, not console)
     * we'll replace the message with something generic.
     *
     * @param EnvelopeV1 $envelope
     * @param Request    $request
     *
     * @return string
     */
    private function redactErrorMessage(EnvelopeV1 $envelope, Request $request): string
    {
        try {
            $code = Code::create($envelope->get(EnvelopeV1::CODE_FIELD))->getName();
        } catch (\Throwable $e) {
            $code = (string)$envelope->get(EnvelopeV1::CODE_FIELD);
        }

        return sprintf('Your message could not be handled (Code:%s).', $code);
    }
}
