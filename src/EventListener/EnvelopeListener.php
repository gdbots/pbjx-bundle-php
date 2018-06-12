<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\EventListener;

use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Envelope;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;

/**
 * Handles the conversion of an Envelope to a symfony response.
 */
final class EnvelopeListener
{
    /* @var Pbjx */
    private $pbjx;

    /* @var LoggerInterface */
    private $logger;

    /**
     * @param Pbjx            $pbjx
     * @param LoggerInterface $logger
     */
    public function __construct(Pbjx $pbjx, ?LoggerInterface $logger = null)
    {
        $this->pbjx = $pbjx;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * @param GetResponseForControllerResultEvent $event
     */
    public function onKernelView(GetResponseForControllerResultEvent $event): void
    {
        $envelope = $event->getControllerResult();
        if (!$envelope instanceof Envelope) {
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

        $envelope->set('ok', Code::OK === $envelope->get('code'));
        $httpCode = $envelope->has('http_code') ? $envelope->get('http_code')->getValue() : 200;
        $array = $envelope->toArray();

        if (isset($array['error_message']) && $redact) {
            if ($httpCode >= 500) {
                $this->logger->error(
                    sprintf(
                        '%s::Message [{pbj_schema}] failed (Code:%s,HttpCode:%s).',
                        $envelope->get('error_name'),
                        $envelope->get('code'),
                        $httpCode
                    ),
                    [
                        'pbj_schema' => $envelope::schema()->getId()->toString(),
                        'pbj'        => $array,
                    ]
                );
            }

            $array['error_message'] = $this->redactErrorMessage($envelope, $request);
        }

        $response = new JsonResponse($array, $httpCode, [
            'Content-Type'       => 'application/json',
            'ETag'               => $envelope->get('etag'),
            'x-pbjx-envelope-id' => (string)$envelope->get('envelope_id'),
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
     * @param Envelope $envelope
     * @param Request  $request
     *
     * @return string
     */
    private function redactErrorMessage(Envelope $envelope, Request $request): string
    {
        try {
            $code = Code::create($envelope->get('code'))->getName();
        } catch (\Throwable $e) {
            $code = $envelope->get('code');
        }

        return sprintf('Your message could not be handled (Code:%s).', $code);
    }
}
