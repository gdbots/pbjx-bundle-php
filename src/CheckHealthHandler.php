<?php

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\CommandHandlerTrait;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Command\CheckHealth;
use Gdbots\Schemas\Pbjx\Event\HealthCheckedV1;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class CheckHealthHandler implements CommandHandler
{
    use CommandHandlerTrait;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * @param CheckHealth $command
     * @param Pbjx $pbjx
     */
    protected function handle(CheckHealth $command, Pbjx $pbjx)
    {
        $event = HealthCheckedV1::create()->set('msg', $command->get('msg'));
        $pbjx->copyContext($command, $event)->publish($event);
        $this->logger->info('CheckHealthHandler published [{pbj_schema}] with message [{msg}].', [
            'msg' => $event->get('msg'),
            'pbj_schema' => $event::schema()->getId()->toString(),
            'pbj' => $event->toArray(),
        ]);
    }
}
