<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\CommandHandlerTrait;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Command\CheckHealth;
use Gdbots\Schemas\Pbjx\Command\CheckHealthV1;
use Gdbots\Schemas\Pbjx\Event\HealthCheckedV1;
use Psr\Log\LoggerInterface;

final class CheckHealthHandler implements CommandHandler
{
    use CommandHandlerTrait;

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
     * @param CheckHealth $command
     * @param Pbjx        $pbjx
     */
    protected function handle(CheckHealth $command, Pbjx $pbjx): void
    {
        $event = HealthCheckedV1::create()->set('msg', $command->get('msg'));
        $pbjx->copyContext($command, $event)->publish($event);
        $this->logger->info('CheckHealthHandler published [{pbj_schema}] with message [{msg}].', [
            'msg'        => $event->get('msg'),
            'pbj_schema' => $event::schema()->getId()->toString(),
            'pbj'        => $event->toArray(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        return [
            CheckHealthV1::schema()->getCurie(),
        ];
    }
}
