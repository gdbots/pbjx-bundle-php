<?php

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Common\Microtime;
use Gdbots\Common\Util\DateUtils;
use Gdbots\Common\Util\NumberUtils;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ReplayEventsCommand extends ContainerAwareCommand
{
    use ConsumerTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pbjx:replay-events')
            ->setDescription('Streams events from the event store for a given stream id and replays them through pbjx->publish.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will stream events from the pbjx event store for a given stream id and re-publish them.

<info>php %command.full_name% --dry-run --hints='{"tenant_id":"123"}' stream-id</info>

EOF
            )
            ->addOption('in-memory', null, InputOption::VALUE_NONE, 'Forces all transports to be "in_memory".  Useful for debugging.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Streams events and renders output but will NOT actually publish.')
            ->addOption('skip-errors', null, InputOption::VALUE_NONE, 'Skip any events that fail to replay.  Generally a bad idea.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of events to publish at a time.', 100)
            ->addOption('batch-delay', null, InputOption::VALUE_REQUIRED, 'Number of milliseconds (1000 = 1 second) to delay between batches.', 1000)
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Replays events where occurred_at is greater than this time (unix timestamp or 16 digit microtime as int).')
            ->addOption('hints', null, InputOption::VALUE_REQUIRED, 'Hints to provide to the event store (json).')
            ->addArgument('stream-id', InputArgument::REQUIRED, 'The stream to replay messages from.  See Gdbots\Schemas\Pbjx\StreamId for details.')
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
        $sinceStr = null;
        $hints = json_decode($input->getOption('hints') ?: '{}', true);

        if (!empty($since)) {
            $since = Microtime::fromString(str_pad($since, 16, '0'));
            $sinceStr = sprintf(
                ' where occurred_at > %s (%s)',
                $since->toString(),
                $since->toDateTime()->format(DateUtils::ISO8601_ZULU)
            );
        }

        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Replaying events from stream "%s"%s', $streamId, $sinceStr));
        $this->useInMemoryTransports($input, $io);
        if (!$this->readyForReplayTraffic($io)) {
            return;
        }

        $this->createConsoleRequest();
        $pbjx = $this->getPbjx();
        $batch = 1;
        $i = 0;
        $replayed = 0;
        $io->comment(sprintf('Processing batch %d from stream "%s"%s', $batch, $streamId, $sinceStr));
        $io->comment(sprintf('hints: %s', json_encode($hints)));

        /** @var Event $event */
        foreach ($pbjx->getEventStore()->streamEvents($streamId, $since, $hints) as $event) {
            ++$i;

            try {
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

                if ($dryRun) {
                    $io->note(sprintf('DRY RUN - Would publish event "%s" here.', $event->get('event_id')));
                } else {
                    $event->isReplay(true);
                    $pbjx->publish($event);
                }

                ++$replayed;

            } catch (\Exception $e) {
                $io->error($e->getMessage());
                $io->note(sprintf('Failed event "%s" json below:', $event->get('event_id')));
                $io->text(json_encode($event));
                $io->newLine(2);

                if (!$skipErrors) {
                    break;
                }
            }

            if (0 === $i % $batchSize) {
                ++$batch;

                if ($batchDelay > 0) {
                    $io->note(sprintf('Pausing for %d milliseconds', $batchDelay));
                    usleep($batchDelay * 1000);
                }

                $io->comment(sprintf('Processing batch %d from stream "%s"%s', $batch, $streamId, $sinceStr));
            }
        }

        $io->newLine();
        $io->success(sprintf('Replayed %s events from stream "%s"%s.', number_format($replayed), $streamId, $sinceStr));
    }
}
