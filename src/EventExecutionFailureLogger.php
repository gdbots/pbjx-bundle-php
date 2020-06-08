<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Event\EventExecutionFailedV1;
use Psr\Log\LoggerInterface;

final class EventExecutionFailureLogger implements EventSubscriber
{
    private LoggerInterface $logger;

    public static function getSubscribedEvents()
    {
        return [
            EventExecutionFailedV1::SCHEMA_CURIE => 'onEventExecutionFailed',
        ];
    }

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onEventExecutionFailed(Message $event, Pbjx $pbjx): void
    {
        $message = sprintf(
            '%s::%s Event subscriber failed to handle message [{pbj_schema}].',
            $event->get(EventExecutionFailedV1::ERROR_NAME_FIELD),
            $event->get(EventExecutionFailedV1::ERROR_CODE_FIELD)
        );

        $this->logger->critical($message, [
            'pbj_schema' => $event::schema()->getId()->toString(),
            'pbj'        => $event->toArray(),
        ]);
    }
}
