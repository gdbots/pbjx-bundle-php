<?php

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\StreamId;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportEventsCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pbjx:export-events')
            ->setDescription('Streams events from the event store for a given stream id and writes them to STDOUT.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will stream events from the pbjx event store for a given stream id and write the json
value of the event on one line (json newline delimited) to STDOUT.

<info>php %command.full_name% --hints='{"tenant_id":"123"}' stream-id</info>

EOF
            )
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of events to export at a time.', 100)
            ->addOption('batch-delay', null, InputOption::VALUE_REQUIRED, 'Number of milliseconds (1000 = 1 second) to delay between batches.', 1000)
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Exports events where occurred_at is greater than this time (unix timestamp or 16 digit microtime as int).')
            ->addOption('hints', null, InputOption::VALUE_REQUIRED, 'Hints to provide to the event store (json).')
            ->addArgument('stream-id', InputArgument::REQUIRED, 'The stream to export messages from.  See Gdbots\Schemas\Pbjx\StreamId for details.')
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
        $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        $errOutput->setVerbosity(OutputInterface::VERBOSITY_NORMAL);

        $streamId = StreamId::fromString($input->getArgument('stream-id'));
        $batchSize = NumberUtils::bound($input->getOption('batch-size'), 1, 1000);
        $batchDelay = NumberUtils::bound($input->getOption('batch-delay'), 100, 600000);
        $since = $input->getOption('since');
        $hints = json_decode($input->getOption('hints') ?: '{}', true);

        if (!empty($since)) {
            $since = Microtime::fromString(str_pad($since, 16, '0'));
        }

        /** @var Pbjx $pbjx */
        $pbjx = $this->getContainer()->get('pbjx');
        $i = 0;

        foreach ($pbjx->getEventStore()->streamEvents($streamId, $since, $hints) as $event) {
            ++$i;

            try {
                echo json_encode($event).PHP_EOL;
            } catch (\Exception $e) {
                $errOutput->writeln($e->getMessage());
            }

            if (0 === $i % $batchSize) {
                if ($batchDelay > 0) {
                    usleep($batchDelay * 1000);
                }
            }
        }
    }
}
