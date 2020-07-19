<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\Util\NumberUtil;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Schemas\Pbjx\StreamId;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ReplayEventsCommand extends Command
{
    use PbjxAwareCommandTrait;

    protected static $defaultName = 'pbjx:replay-events';

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
    }

    protected function configure()
    {
        $provider = $this->container->getParameter('gdbots_pbjx.event_store.provider');

        $this
            ->setDescription("Pipes events from the EventStore ({$provider}) and replays them through pbjx->publish.")
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will pipe events from the EventStore ({$provider})
for the given StreamId if provided or all events and re-publish them through pbjx->publish.

<info>php %command.full_name% --dry-run --tenant-id=client1 'stream-id'</info>

EOF
            )
            ->addOption(
                'in-memory',
                null,
                InputOption::VALUE_NONE,
                'Forces all transports to be "in_memory". Useful for debugging or ensuring sequential processing.'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Pipes events and renders output but will NOT actually publish.'
            )
            ->addOption(
                'skip-errors',
                null,
                InputOption::VALUE_NONE,
                'Skip any events that fail to replay. Generally a bad idea.'
            )
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of events to publish at a time.',
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
                'Replays events where occurred_at is greater than this time ' .
                '(unix timestamp or 16 digit microtime as int).'
            )
            ->addOption(
                'until',
                null,
                InputOption::VALUE_REQUIRED,
                'Replays events where occurred_at is less than this time ' .
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
                'The stream to replay messages from. See Gdbots\Schemas\Pbjx\StreamId for details.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = $input->getOption('dry-run');
        $skipErrors = $input->getOption('skip-errors');
        $batchSize = NumberUtil::bound((int)$input->getOption('batch-size'), 1, 1000);
        $batchDelay = NumberUtil::bound((int)$input->getOption('batch-delay'), 100, 600000);
        $since = $input->getOption('since');
        $until = $input->getOption('until');
        $context = $input->getOption('context') ?: '{}';
        if (strpos($context, '{') === false) {
            $context = base64_decode($context);
        }
        $context = json_decode($context, true);
        $context['tenant_id'] = (string)$input->getOption('tenant-id');
        $context['skip_errors'] = $skipErrors;
        $streamId = $input->getArgument('stream-id') ? StreamId::fromString($input->getArgument('stream-id')) : null;

        if (!empty($since)) {
            $since = Microtime::fromString(str_pad($since, 16, '0'));
        }

        if (!empty($until)) {
            $until = Microtime::fromString(str_pad($until, 16, '0'));
        }

        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Replaying events from stream "%s"', $streamId ?? 'ALL'));
        $io->comment('context: ' . json_encode($context));
        $this->useInMemoryTransports($input, $io);
        if (!$this->readyForPbjxTraffic($io)) {
            return self::FAILURE;
        }

        $this->createConsoleRequest();
        $pbjx = $this->getPbjx();
        $batch = 1;
        $i = 0;
        $replayed = 0;
        $io->comment(sprintf('Processing batch %d from stream "%s".', $batch, $streamId ?? 'ALL'));
        $io->newLine();

        $generator = $streamId
            ? $pbjx->getEventStore()->pipeEvents($streamId, $since, $until, $context)
            : $pbjx->getEventStore()->pipeAllEvents($since, $until, $context);

        foreach ($generator as $result) {
            ++$i;
            /** @var Message $event */
            if ($result instanceof Message) {
                $event = $result;
                $sid = $streamId;
            } else {
                [$event, $sid] = $result;
            }

            try {
                $output->writeln(
                    sprintf(
                        '<info>%d.</info> <comment>stream:</comment>%s, <comment>occurred_at:</comment>%s, ' .
                        '<comment>curie:</comment>%s, <comment>event_id:</comment>%s',
                        $i,
                        $sid,
                        $event->get('occurred_at'),
                        $event::schema()->getCurie()->toString(),
                        $event->get('event_id')
                    )
                );

                if ($dryRun) {
                    $io->note(sprintf('DRY RUN - Would publish event "%s" here.', $event->get('event_id')));
                } else {
                    $event->isReplay(true);
                    $pbjx->publish($event);
                }

                ++$replayed;
            } catch (\Throwable $e) {
                $io->error(sprintf('%d. %s', $i, $e->getMessage()));
                $io->note(sprintf(
                    '%d. Failed event "%s" json below:',
                    $i,
                    $event->get('event_id')
                ));
                $io->text(json_encode($event, JSON_PRETTY_PRINT));
                $io->newLine(2);

                if (!$skipErrors) {
                    throw $e;
                }
            }

            if (0 === $i % $batchSize) {
                ++$batch;

                if ($batchDelay > 0) {
                    $io->newLine();
                    $io->note(sprintf('Pausing for %d milliseconds.', $batchDelay));
                    usleep($batchDelay * 1000);
                }

                $io->comment(sprintf('Processing batch %d.', $batch));
                $io->newLine();
            }
        }

        $io->newLine();
        $io->success(sprintf('Replayed %s events from stream "%s".', number_format($replayed), $streamId ?? 'ALL'));

        return self::SUCCESS;
    }
}
