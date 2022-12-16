<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\Util\NumberUtil;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Schemas\Pbjx\StreamId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[AsCommand(name: 'pbjx:export-events')]
final class ExportEventsCommand extends Command
{
    use PbjxAwareCommandTrait;

    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $provider = $this->container->getParameter('gdbots_pbjx.event_store.provider');

        $this
            ->setDescription("Pipes events from the EventStore ({$provider}) to STDOUT")
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will pipe events from the EventStore ({$provider})
for the given StreamId if provided or all events and write the json value of the event on
one line (json newline delimited) to STDOUT.

<info>php %command.full_name% --tenant-id=client1 'stream-id'</info>

EOF
            )
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of events to export at a time.',
                100
            )
            ->addOption(
                'batch-delay',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of milliseconds (1000 = 1 second) to delay between batches.',
                1000
            )
            ->addOption(
                'since',
                null,
                InputOption::VALUE_REQUIRED,
                'Exports events where occurred_at is greater than this time ' .
                '(unix timestamp or 16 digit microtime as int).'
            )
            ->addOption(
                'until',
                null,
                InputOption::VALUE_REQUIRED,
                'Exports events where occurred_at is less than this time ' .
                '(unix timestamp or 16 digit microtime as int).'
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
                InputArgument::OPTIONAL,
                'The stream to export messages from. See Gdbots\Schemas\Pbjx\StreamId for details.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        $errOutput->setVerbosity(OutputInterface::VERBOSITY_NORMAL);

        $batchSize = NumberUtil::bound((int)$input->getOption('batch-size'), 1, 1000);
        $batchDelay = NumberUtil::bound((int)$input->getOption('batch-delay'), 100, 600000);
        $since = $input->getOption('since');
        $until = $input->getOption('until');
        $context = $input->getOption('context') ?: '{}';
        if (!str_contains($context, '{')) {
            $context = base64_decode($context);
        }
        $context = json_decode($context, true);
        $context['tenant_id'] = (string)$input->getOption('tenant-id');
        $streamId = $input->getArgument('stream-id') ? StreamId::fromString($input->getArgument('stream-id')) : null;

        if (!empty($since)) {
            $since = Microtime::fromString(str_pad($since, 16, '0'));
        }

        if (!empty($until)) {
            $until = Microtime::fromString(str_pad($until, 16, '0'));
        }

        $generator = $streamId
            ? $this->getPbjx()->getEventStore()->pipeEvents($streamId, $since, $until, $context)
            : $this->getPbjx()->getEventStore()->pipeAllEvents($since, $until, $context);

        $i = 0;
        foreach ($generator as $result) {
            ++$i;
            if ($result instanceof Message) {
                $event = $result;
            } else {
                [$event] = $result;
            }

            try {
                echo json_encode($event) . PHP_EOL;
            } catch (\Throwable $e) {
                $errOutput->writeln($e->getMessage());
            }

            if (0 === $i % $batchSize) {
                if ($batchDelay > 0) {
                    usleep($batchDelay * 1000);
                }
            }
        }

        return self::SUCCESS;
    }
}
