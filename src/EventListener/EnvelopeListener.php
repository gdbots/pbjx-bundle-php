<?php

namespace Gdbots\Bundle\PbjxBundle\EventListener;

use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Envelope;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\JsonResponse;
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

        $event->getRequest()->setRequestFormat('json');

        try {
            $pbjxEvent = new PbjxEvent($envelope);
            $this->pbjx->trigger($envelope, PbjxEvents::SUFFIX_BIND, $pbjxEvent, false);
            $this->pbjx->trigger($envelope, PbjxEvents::SUFFIX_VALIDATE, $pbjxEvent, false);
            $this->pbjx->trigger($envelope, PbjxEvents::SUFFIX_ENRICH, $pbjxEvent, false);
        } catch (\Exception $e) {
            $this->logger->error('Error running pbjx->trigger on envelope.', ['exception' => $e]);
        }

        $envelope->set('ok', Code::OK === $envelope->get('code'));

        $response = new JsonResponse(
            $envelope->toArray(),
            $envelope->has('http_status_code') ? $envelope->get('http_status_code')->getValue() : 200,
            [
                'Content-Type' => 'application/json',
                'ETag' => $envelope->get('etag', 'test'),
                'x-pbjx-envelope-id' => $envelope->get('envelope_id'),
            ]
        );

        $event->setResponse($response);
    }
}
