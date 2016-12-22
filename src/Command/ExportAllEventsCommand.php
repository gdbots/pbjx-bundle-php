<?php

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportAllEventsCommand extends ContainerAwareCommand
{
    use PbjxAwareCommandTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pbjx:export-all-events')
            ->setDescription('Streams ALL events from the event store and writes them to STDOUT.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will stream ALL events from the pbjx event store for and write the json
value of the event on one line (json newline delimited) to STDOUT.

<info>php %command.full_name% --hint='{"tenant_id":"123"}'</info>

EOF
            )
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of events to export at a time.', 100)
            ->addOption('batch-delay', null, InputOption::VALUE_REQUIRED, 'Number of milliseconds (1000 = 1 second) to delay between batches.', 1000)
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Exports events where occurred_at is greater than this time (unix timestamp or 16 digit microtime as int).')
            ->addOption('until', null, InputOption::VALUE_REQUIRED, 'Exports events where occurred_at is less than this time (unix timestamp or 16 digit microtime as int).')
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
        $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        $errOutput->setVerbosity(OutputInterface::VERBOSITY_NORMAL);

        $batchSize = NumberUtils::bound($input->getOption('batch-size'), 1, 1000);
        $batchDelay = NumberUtils::bound($input->getOption('batch-delay'), 100, 600000);
        $since = $input->getOption('since');
        $until = $input->getOption('until');
        $hints = json_decode($input->getOption('hints') ?: '{}', true);

        if (!empty($since)) {
            $since = Microtime::fromString(str_pad($since, 16, '0'));
        }

        if (!empty($until)) {
            $until = Microtime::fromString(str_pad($until, 16, '0'));
        }

        $pbjx = $this->getPbjx();
        $i = 0;

        $callback = function(Event $event, StreamId $streamId) use ($errOutput, $batchSize, $batchDelay, &$i) {
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
        };

        $pbjx->getEventStore()->streamAllEvents($callback, $since, $until, $hints);
    }
}
