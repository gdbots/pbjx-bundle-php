<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Command\CheckHealthV1;
use Gdbots\Schemas\Pbjx\Event\HealthCheckedV1;
use Psr\Log\LoggerInterface;

final class CheckHealthHandler implements CommandHandler
{
    private LoggerInterface $logger;

    public static function handlesCuries(): array
    {
        return [
            CheckHealthV1::SCHEMA_CURIE,
        ];
    }

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function handleCommand(Message $command, Pbjx $pbjx): void
    {
        $event = HealthCheckedV1::create()->set(
            HealthCheckedV1::MSG_FIELD,
            $command->get(CheckHealthV1::MSG_FIELD)
        );
        $pbjx->copyContext($command, $event)->publish($event);
        $this->logger->info('CheckHealthHandler published [{pbj_schema}] with message [{msg}].', [
            'msg'        => $event->get(HealthCheckedV1::MSG_FIELD),
            'pbj_schema' => $event::schema()->getId()->toString(),
            'pbj'        => $event->toArray(),
        ]);
    }
}
