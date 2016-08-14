<?php

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ReindexEventsCommand extends ContainerAwareCommand
{
    use ConsumerTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pbjx:reindex-events')
            ->setDescription('Streams events from the event store for a given stream id and reindexes them.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will stream events from the pbjx event store for a given stream id and reindex them.

<info>php %command.full_name% --dry-run --hints='{"tenant_id":"123"}' stream-id</info>

EOF
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Streams events and renders output but will NOT actually reindex.')
            ->addOption('skip-errors', null, InputOption::VALUE_NONE, 'Skip any batches that fail to reindex.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of events to reindex at a time.', 100)
            ->addOption('batch-delay', null, InputOption::VALUE_REQUIRED, 'Number of milliseconds (1000 = 1 second) to delay between batches.', 1000)
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Reindex events where occurred_at is greater than this time (unix timestamp or 16 digit microtime as int).')
            ->addOption('hints', null, InputOption::VALUE_REQUIRED, 'Hints to provide to the event store (json).')
            ->addArgument('stream-id', InputArgument::REQUIRED, 'The stream to reindex messages from.  See Gdbots\Schemas\Pbjx\StreamId for details.')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $streamId = StreamId::fromString($input->getArgument('stream-id'));
        $dryRun = $input->getOption('dry-run');
        $skipErrors = $input->getOption('skip-errors');
        $batchSize = NumberUtils::bound($input->getOption('batch-size'), 1, 1000);
        $batchDelay = NumberUtils::bound($input->getOption('batch-delay'), 100, 600000);
        $since = $input->getOption('since');
        $hints = json_decode($input->getOption('hints') ?: '{}', true);
        $hints['reindexing'] = true;

        if (!empty($since)) {
            $since = Microtime::fromString(str_pad($since, 16, '0'));
        }

        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Reindexing events from stream "%s"', $streamId));
        if (!$this->readyForReplayTraffic($io)) {
            return;
        }

        $this->createConsoleRequest();
        $pbjx = $this->getPbjx();
        $batch = 1;
        $i = 0;
        $reindexed = 0;
        $queue = [];
        $io->comment(sprintf('Processing batch %d from stream "%s".', $batch, $streamId));
        $io->comment(sprintf('hints: %s', json_encode($hints)));
        $io->newLine();

        /** @var Event $event */
        foreach ($pbjx->getEventStore()->streamEvents($streamId, $since, $hints) as $event) {
            ++$i;
            $output->writeln(
                sprintf(
                    '<info>%d.</info> <comment>occurred_at:</comment>%s, <comment>curie:</comment>%s, ' .
                    '<comment>event_id:</comment>%s',
                    $i,
                    $event->get('occurred_at'),
                    $event::schema()->getCurie()->toString(),
                    $event->get('event_id')
                )
            );
            $queue[] = $event->freeze();

            if (0 === $i % $batchSize) {
                $this->reindex($queue, $reindexed, $pbjx, $io, $batch, $dryRun, $skipErrors);
                ++$batch;

                if ($batchDelay > 0) {
                    $io->newLine();
                    $io->note(sprintf('Pausing for %d milliseconds.', $batchDelay));
                    usleep($batchDelay * 1000);
                }

                $io->comment(sprintf('Processing batch %d from stream "%s".', $batch, $streamId));
                $io->newLine();
            }
        }

        $this->reindex($queue, $reindexed, $pbjx, $io, $batch, $dryRun, $skipErrors);
        $io->newLine();
        $io->success(sprintf('Reindexed %s events from stream "%s".', number_format($reindexed), $streamId));
    }

    /**
     * @param array $queue
     * @param int $reindexed
     * @param Pbjx $pbjx
     * @param SymfonyStyle $io
     * @param int $batch
     * @param bool $dryRun
     * @param bool $skipErrors
     *
     * @throws \Exception
     */
    protected function reindex(
        array &$queue,
        &$reindexed,
        Pbjx $pbjx,
        SymfonyStyle $io,
        $batch,
        $dryRun = false,
        $skipErrors = false
    ) {
        if ($dryRun) {
            $io->note(sprintf('DRY RUN - Would reindex event batch %d here.', $batch));
        } else {
            try {
                $pbjx->getEventSearch()->index($queue);
            } catch (\Exception $e) {
                $io->error($e->getMessage());
                $io->note(sprintf('Failed to index batch %d.', $batch));
                $io->newLine(2);

                if (!$skipErrors) {
                    throw $e;
                }
            }
        }

        $reindexed += count($queue);
        $queue = [];
    }
}
