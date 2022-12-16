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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[AsCommand(name: 'pbjx:reindex-events')]
final class ReindexEventsCommand extends Command
{
    use PbjxAwareCommandTrait;

    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $storeProvider = $this->container->getParameter('gdbots_pbjx.event_store.provider');
        $searchProvider = $this->container->getParameter('gdbots_pbjx.event_search.provider');

        $this
            ->setDescription("Pipes events from the EventStore ({$storeProvider}) and reindexes them")
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will pipe events from the EventStore ({$storeProvider})
for the given StreamId if provided or all events and reindex them into the EventSearch ({$searchProvider}) service.

<info>php %command.full_name% --dry-run --tenant-id=client1 'stream-id'</info>

EOF
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Pipes events and renders output but will NOT actually reindex.'
            )
            ->addOption(
                'skip-errors',
                null,
                InputOption::VALUE_NONE,
                'Skip any batches that fail to reindex.'
            )
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of events to reindex at a time.',
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
                'Reindex events where occurred_at is greater than this time ' .
                '(unix timestamp or 16 digit microtime as int).'
            )
            ->addOption(
                'until',
                null,
                InputOption::VALUE_REQUIRED,
                'Reindex events where occurred_at is less than this time ' .
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
                'The stream to reindex messages from. See Gdbots\Schemas\Pbjx\StreamId for details.'
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
        if (!str_contains($context, '{')) {
            $context = base64_decode($context);
        }
        $context = json_decode($context, true);
        $context['tenant_id'] = (string)$input->getOption('tenant-id');
        $context['skip_errors'] = $skipErrors;
        $context['reindexing'] = true;
        $streamId = $input->getArgument('stream-id') ? StreamId::fromString($input->getArgument('stream-id')) : null;

        if (!empty($since)) {
            $since = Microtime::fromString(str_pad($since, 16, '0'));
        }

        if (!empty($until)) {
            $until = Microtime::fromString(str_pad($until, 16, '0'));
        }

        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Reindexing events from stream "%s"', $streamId ?? 'ALL'));
        $io->comment('context: ' . json_encode($context));
        $io->newLine();

        if (!$this->readyForPbjxTraffic($io)) {
            return self::FAILURE;
        }

        $this->createConsoleRequest();
        $batch = 1;
        $i = 0;
        $reindexed = 0;
        $queue = [];

        $generator = $streamId
            ? $this->getPbjx()->getEventStore()->pipeEvents($streamId, $since, $until, $context)
            : $this->getPbjx()->getEventStore()->pipeAllEvents($since, $until, $context);

        foreach ($generator as $result) {
            /** @var Message $event */
            if ($result instanceof Message) {
                $event = $result;
                $sid = $streamId;
            } else {
                [$event, $sid] = $result;
            }

            if (!$event::schema()->hasMixin('gdbots:pbjx:mixin:indexed')) {
                $io->note(sprintf(
                    'IGNORING - Event [%s] does not have mixin [gdbots:pbjx:mixin:indexed].',
                    $event->generateMessageRef(),
                ));
                continue;
            }

            ++$i;
            $queue[] = $event->freeze();

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

            if (count($queue) >= $batchSize) {
                $events = $queue;
                $queue = [];
                $reindexed += $this->reindexBatch($io, $events, $context, $batch, $dryRun, $skipErrors);
                ++$batch;

                if ($batchDelay > 0) {
                    usleep($batchDelay * 1000);
                }
            }
        }

        $reindexed += $this->reindexBatch($io, $queue, $context, $batch, $dryRun, $skipErrors);
        $io->newLine();
        $io->success(sprintf(
            'Reindexed %s of %s events from stream "%s".',
            number_format($reindexed),
            number_format($i),
            $streamId ?? 'ALL'
        ));

        return self::SUCCESS;
    }

    protected function reindexBatch(SymfonyStyle $io, array $events, array $context, int $batch, bool $dryRun, bool $skipErrors): int
    {
        if (empty($events)) {
            return 0;
        }

        if ($dryRun) {
            $io->note(sprintf('DRY RUN - Would reindex event batch %d here.', $batch));
            return count($events);
        }

        try {
            return $this->reindex($events, $context, $skipErrors);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $io->note(sprintf('Failed to index batch %d.', $batch));
            $io->newLine(2);

            if (!$skipErrors) {
                throw $e;
            }
        }

        return 0;
    }

    protected function reindex(array $events, array $context, bool $skipErrors): int
    {
        $count = count($events);
        if ($count === 0) {
            return 0;
        }

        $search = $this->getPbjx()->getEventSearch();

        try {
            $search->indexEvents($events, $context);
            return $count;
        } catch (\Throwable $e) {
            // in case of failure try again with smaller batch sizes and delay
            $chunks = array_chunk($events, (int)(ceil($count / 10)));
            $indexed = 0;

            foreach ($chunks as $chunk) {
                try {
                    usleep(100000);
                    $search->indexEvents($chunk, $context);
                    $indexed += count($chunk);
                } catch (\Throwable $e2) {
                    if (!$skipErrors) {
                        throw $e2;
                    }
                }
            }

            return $indexed;
        }
    }
}
