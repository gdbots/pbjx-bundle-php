<?php

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Bundle\PbjxBundle\ContainerAwareServiceLocator;
use Gdbots\Common\Util\NumberUtils;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
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
            ->setDescription('Streams events from the event store and replays them through pbjx->publish.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> will stream events from the pbjx event store and re-publish them.

<info>php %command.full_name% --dry-run 'stream-id'</info>

EOF
            )
            ->addOption('in-memory', null, InputOption::VALUE_NONE, 'Forces all transports to be "in_memory".  Useful for debugging.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Streams events and renders output but will NOT actually publish.')
            ->addOption('skip-errors', null, InputOption::VALUE_NONE, 'Skip any events that fail to replay.  Generally a bad idea.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of events to publish at a time.', 100)
            ->addOption('batch-delay', null, InputOption::VALUE_REQUIRED, 'Number of seconds to delay between batches.', 5)
            ->addArgument('stream-id', InputArgument::REQUIRED, 'The stream to replay messages from.')
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
        $streamId = $input->getArgument('stream-id');
        $dryRun = $input->getOption('dry-run') ?: false;
        $skipErrors = $input->getOption('skip-errors') ?: false;
        $batchSize = NumberUtils::bound($input->getOption('batch-size'), 1, 500);
        $batchDelay = NumberUtils::bound($input->getOption('batch-delay'), 0, 600);

        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Replaying events from stream "%s"', $streamId));

        /*
         * running transports "in-memory" means the command/request handlers and event
         * subscribers to pbjx messages will happen in this process and not run through
         * kinesis, gearman, sqs, etc.  Generally used for debugging.
         */
        if ($input->getOption('in-memory')) {
            $locator = $this->getContainer()->get('gdbots_pbjx.service_locator');
            if ($locator instanceof ContainerAwareServiceLocator) {
                $locator->forceTransportsToInMemory();
                $io->note('Using in_memory transports.');
            }
        }

        $this->createConsoleRequest();
        $pbjx = $this->getPbjx();
        $batch = 1;
        $i = 0;
        $replayed = 0;
        $io->comment(sprintf('Processing batch %d from stream "%s"', $batch, $streamId));

        /** @var Event $event */
        foreach ($pbjx->getEventStore()->streamEvents($streamId) as $event) {
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
                    $io->note(sprintf('Pausing for %d seconds', $batchDelay));
                    sleep($batchDelay);
                }

                $io->comment(sprintf('Processing batch %d from stream "%s"', $batch, $streamId));
            }
        }

        $io->newLine();
        $io->success(sprintf('Replayed %s events from stream "%s".', number_format($replayed), $streamId));
    }
}
