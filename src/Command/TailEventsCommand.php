<?php

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Common\Microtime;
use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TailEventsCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pbjx:tail-events')
            ->setDescription('Tails events from the event store for a given stream id and writes them to STDOUT.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will tail events from the pbjx event store for a given stream id and write the json
value of the event on one line (json newline delimited) to STDOUT.

<info>php %command.full_name% --hints='{"tenant_id":"123"}' stream-id</info>

EOF
            )
            ->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Number of seconds to wait between updates.', 3)
            ->addOption('hints', null, InputOption::VALUE_REQUIRED, 'Hints to provide to the event store (json).')
            ->addArgument('stream-id', InputArgument::REQUIRED, 'The stream to tail messages from.  See Gdbots\Schemas\Pbjx\StreamId for details.')
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
        $interval = NumberUtils::bound($input->getOption('interval'), 1, 60);
        $hints = json_decode($input->getOption('hints') ?: '{}', true);

        /** @var Pbjx $pbjx */
        $pbjx = $this->getContainer()->get('pbjx');
        $since = Microtime::create();

        while (true) {
            $event = null;

            /** @var Event $event */
            foreach ($pbjx->getEventStore()->streamEvents($streamId, $since, $hints) as $event) {
                try {
                    echo json_encode($event).PHP_EOL;
                } catch (\Exception $e) {
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