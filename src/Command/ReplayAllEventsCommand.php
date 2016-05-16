<?php

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Bundle\PbjxBundle\ContainerAwareServiceLocator;
use Gdbots\Common\Microtime;
use Gdbots\Common\Util\DateUtils;
use Gdbots\Common\Util\NumberUtils;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ReplayAllEventsCommand extends ContainerAwareCommand
{
    use ConsumerTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pbjx:replay-all-events')
            ->setDescription('Streams ALL events from the event store and replays them through pbjx->publish.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will stream ALL events from the pbjx event store and re-publish them.

<info>php %command.full_name% --dry-run --hint='{"tenant_id":"123"}'</info>

EOF
            )
            ->addOption('in-memory', null, InputOption::VALUE_NONE, 'Forces all transports to be "in_memory".  Useful for debugging.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Streams events and renders output but will NOT actually publish.')
            ->addOption('skip-errors', null, InputOption::VALUE_NONE, 'Skip any events that fail to replay.  Generally a bad idea.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of events to publish at a time.', 100)
            ->addOption('batch-delay', null, InputOption::VALUE_REQUIRED, 'Number of seconds to delay between batches.', 5)
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Replays events where occurred_at is greater than this time (unix timestamp or 16 digit microtime as int).')
            ->addOption('hints', null, InputOption::VALUE_REQUIRED, 'Hints to provide to the event store (json).')
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
        $dryRun = $input->getOption('dry-run');
        $skipErrors = $input->getOption('skip-errors');
        $batchSize = NumberUtils::bound($input->getOption('batch-size'), 1, 500);
        $batchDelay = NumberUtils::bound($input->getOption('batch-delay'), 0, 600);
        $since = $input->getOption('since');
        $sinceStr = null;
        $hintsJson = $input->getOption('hints');
        $hints = [];

        if (!empty($since)) {
            $since = Microtime::fromString(str_pad($since, 16, '0'));
            $sinceStr = sprintf(
                ' where occurred_at > %s (%s)',
                $since->toString(),
                $since->toDateTime()->format(DateUtils::ISO8601_ZULU)
            );
        }

        if (!empty($hintsJson)) {
            $hints = json_decode($hintsJson, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new \InvalidArgumentException(sprintf(
                    'The hints option [%s] provided is not valid json.  Error: %s',
                    $hintsJson,
                    json_last_error_msg()
                ));
            }
        }
        $hints['skip_errors'] = $skipErrors;

        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Replaying events from ALL streams %s', $sinceStr));

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
        $io->comment(sprintf('Processing batch %d from ALL streams %s', $batch, $sinceStr));
        $io->comment(sprintf('hints: %s', $hintsJson));

        $callback = function(Event $event) {
            echo json_encode($event).PHP_EOL;
        };

        $pbjx->getEventStore()->streamAllEvents($callback, $since, $hints);

        /** @var Event $event */
        /*
        foreach ($pbjx->getEventStore()->streamAllEvents($callback, $since, $hints) as $event) {
            if (!$event instanceof Event) {
                continue;
            }

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

                $io->comment(sprintf('Processing batch %d from ALL streams %s', $batch, $sinceStr));
                $io->comment(sprintf('hints: %s', $hintsJson));
            }
        }
        */

        $io->newLine();
        $io->success(sprintf('Replayed %s events from ALL streams %s.', number_format($replayed), $sinceStr));
    }
}
