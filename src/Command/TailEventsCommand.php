<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class TailEventsCommand extends ContainerAwareCommand
{
    use PbjxAwareCommandTrait;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pbjx:tail-events')
            ->setDescription('Tails events from the EventStore for a given stream id and writes them to STDOUT.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will tail events from the pbjx EventStore for a given 
stream id and write the json value of the event on one line (json newline delimited) to STDOUT.

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
                'The stream to tail messages from.  See Gdbots\Schemas\Pbjx\StreamId for details.'
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        $errOutput->setVerbosity(OutputInterface::VERBOSITY_NORMAL);

        $interval = NumberUtils::bound($input->getOption('interval'), 1, 60);
        $context = json_decode($input->getOption('context') ?: '{}', true);
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

            if ($event instanceof Event) {
                $since = $event->get('occurred_at');
            }

            sleep($interval);
        }
    }
}
