<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Event\EventExecutionFailed;
use Psr\Log\LoggerInterface;

final class EventExecutionFailureLogger implements EventSubscriber
{
    /** @var LoggerInterface */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param EventExecutionFailed $event
     * @param Pbjx                 $pbjx
     */
    public function onEventExecutionFailed(EventExecutionFailed $event, Pbjx $pbjx): void
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

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'gdbots:pbjx:event:event-execution-failed' => 'onEventExecutionFailed',
        ];
    }
}
