<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\Util\NumberUtil;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1Mixin;
use Gdbots\Schemas\Pbjx\StreamId;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class TailEventsCommand extends Command
{
    use PbjxAwareCommandTrait;

    protected static $defaultName = 'pbjx:tail-events';

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
    }

    protected function configure()
    {
        $provider = $this->container->getParameter('gdbots_pbjx.event_store.provider');

        $this
            ->setDescription("Tails events from the EventStore ({$provider}) for a given stream id to STDOUT")
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will tail events from the EventStore ({$provider})
for the given stream id and write the json value of the event on one line
(json newline delimited) to STDOUT.

<info>php %command.full_name% --tenant-id=client1 'stream-id'</info>

EOF
            )
            ->addOption(
                'interval',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of seconds to wait between updates.',
                3
            )
            ->addOption(
                'context',
                null,
                InputOption::VALUE_REQUIRED,
                'Context to provide to the EventStore (json).'
            )
            ->addOption(
                'tenant-id',
                null,
                InputOption::VALUE_REQUIRED,
                'Tenant Id to use for this operation.'
            )
            ->addArgument(
                'stream-id',
                InputArgument::REQUIRED,
                'The stream to tail messages from. See Gdbots\Schemas\Pbjx\StreamId for details.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        $errOutput->setVerbosity(OutputInterface::VERBOSITY_NORMAL);

        $interval = NumberUtil::bound((int)$input->getOption('interval'), 1, 60);
        $context = $input->getOption('context') ?: '{}';
        if (strpos($context, '{') === false) {
            $context = base64_decode($context);
        }
        $context = json_decode($context, true);
        $context['tenant_id'] = (string)$input->getOption('tenant-id');
        $streamId = StreamId::fromString($input->getArgument('stream-id'));

        $eventStore = $this->getPbjx()->getEventStore();
        $since = Microtime::create();

        while (true) {
            $event = null;
            $slice = $eventStore->getStreamSlice($streamId, $since, 25, true, false, $context);

            foreach ($slice as $event) {
                try {
                    echo json_encode($event) . PHP_EOL;
                } catch (\Throwable $e) {
                    $errOutput->writeln($e->getMessage());
                }
            }

            if ($event instanceof Message) {
                $since = $event->get(EventV1Mixin::OCCURRED_AT_FIELD);
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }
}
