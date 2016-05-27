<?php

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Pbjx\Consumer\GearmanConsumer;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GearmanConsumerCommand extends ContainerAwareCommand
{
    use ConsumerTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pbjx:gearman-consumer')
            ->setDescription('Creates a gearman consumer and runs up to the max-runtime.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will create a gearman consumer and listen for jobs on the provided channels.
Gearman server connections are determined by the config parameter "gdbots_pbjx.transport.gearman.servers".

In most cases you'll set this up as a daemon and always respawn after it shutdowns (due to max-runtime option).
Max runtime is used so any php memory issues are cleaned up regularly.

<info>php %command.full_name% --channel=pbjx_commands --channel=pbjx_events --max-runtime=300 --id=pbjx-worker1</info>

EOF
            )
            ->addOption(
                'channel',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'The gearman channel (aka function) this process will handle.',
                ['pbjx_commands', 'pbjx_events']
            )
            ->addOption(
                'max-runtime',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of seconds to allow this consumer to run before shutting down.',
                300
            )
            ->addOption(
                'id',
                null,
                InputOption::VALUE_REQUIRED,
                'Optional id for the gearman consumer. Gets combined with "cloud.instance_id" and "gdbots_pbjx.transport.gearman.channel_prefix".'
            )
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
        $channels = $input->getOption('channel');
        $maxRuntime = (int) $input->getOption('max-runtime');
        $id = $input->getOption('id');

        $container = $this->getContainer();
        $servers = $container->getParameter('gdbots_pbjx.transport.gearman.servers');
        $timeout = (int) $container->getParameter('gdbots_pbjx.transport.gearman.timeout');
        $prefix = $container->getParameter('gdbots_pbjx.transport.gearman.channel_prefix');
        $instanceId = $container->getParameter('cloud_instance_id');
        $workerId = $id ? sprintf('%s_%s%s', $instanceId, $prefix, $id) : null;

        if (!empty($prefix)) {
            $channels = array_map(function ($channel) use ($prefix) { return $prefix . $channel; }, $channels);
        }

        $this->createConsoleRequest();
        $consumer = new GearmanConsumer(
            $this->getPbjxServiceLocator(), $workerId, $channels, $servers, $timeout, $this->getLogger()
        );
        $consumer->run($maxRuntime);
    }
}
