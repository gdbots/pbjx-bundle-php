<?php

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
class EnvelopeListener
{
    /* @var Pbjx */
    protected $pbjx;

    /* @var LoggerInterface */
    protected $logger;

    /**
     * @param Pbjx $pbjx
     * @param LoggerInterface|null $logger
     */
    public function __construct(Pbjx $pbjx, LoggerInterface $logger = null)
    {
        $this->pbjx = $pbjx;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * @param GetResponseForControllerResultEvent $event
     */
    public function onKernelView(GetResponseForControllerResultEvent $event)
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
        } catch (\Exception $e) {
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
                        'pbj' => $array,
                    ]
                );
            }

            $array['error_message'] = $this->redactErrorMessage($envelope, $request);
        }

        $event->setResponse(new JsonResponse($array, $httpCode, [
            'Content-Type' => 'application/json',
            'ETag' => $envelope->get('etag'),
            'x-pbjx-envelope-id' => $envelope->get('envelope_id'),
        ]));
    }

    /**
     * It's generally not safe to render exception messages to the outside world.
     * So when we're pushing an error to the outside world (http, not console)
     * we'll replace the message with something generic.
     *
     * @param Envelope $envelope
     * @param Request $request
     *
     * @return string
     */
    protected function redactErrorMessage(Envelope $envelope, Request $request)
    {
        try {
            $code = Code::create($envelope->get('code'))->getName();
        } catch (\Exception $e) {
            $code = $envelope->get('code');
        }

        return sprintf('Your message could not be handled (Code:%s).', $code);
    }
}
