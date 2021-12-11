<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Pbjx\Pbjx;
use Psr\Log\LoggerInterface;

final class EventExecutionFailureLogger implements EventSubscriber
{
    private LoggerInterface $logger;

    public static function getSubscribedEvents(): array
    {
        return [
            'gdbots:pbjx:event:event-execution-failed' => 'onEventExecutionFailed',
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
            $event->get('error_name'),
            $event->get('error_code')
        );

        $this->logger->critical($message, [
            'pbj_schema' => $event::schema()->getId()->toString(),
            'pbj'        => $event->toArray(),
        ]);
    }
}
