<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Event\HealthCheckedV1;
use Psr\Log\LoggerInterface;

final class CheckHealthHandler implements CommandHandler
{
    private LoggerInterface $logger;

    public static function handlesCuries(): array
    {
        return [
            'gdbots:pbjx:command:check-health',
        ];
    }

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function handleCommand(Message $command, Pbjx $pbjx): void
    {
        $event = HealthCheckedV1::create()->set('msg', $command->get('msg'));
        $pbjx->copyContext($command, $event)->publish($event);
        $this->logger->info('CheckHealthHandler published [{pbj_schema}] with message [{msg}].', [
            'msg'        => $event->get('msg'),
            'pbj_schema' => $event::schema()->getId()->toString(),
            'pbj'        => $event->toArray(),
        ]);
    }
}
