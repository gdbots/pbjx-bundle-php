<?php

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Pbjx\Consumer\GearmanConsumer;
use Gdbots\Pbjx\ServiceLocator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class GearmanConsumerCommand extends ContainerAwareCommand
{
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
Gearman servers connections are determined by the config parameter "gdbots_pbjx.transport.gearman.servers".

In most cases you'll set this up as a daemon and always respawn after it shutdowns (due to max-runtime option).
Max runtime is used so any php memory issues are cleaned up regularly.

<info>php %command.full_name% --channels=pbjx_commands,pbjx_events --max-runtime=300 --id=pbjx-worker1</info>

EOF
            )
            ->addOption(
                'channels',
                null,
                InputOption::VALUE_REQUIRED,
                'A comma delimited list of gearman channels (aka functions) this process will handle.',
                'pbjx_commands,pbjx_events'
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
        $channels = explode(',', $input->getOption('channels'));
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

    /**
     * @return ServiceLocator
     */
    protected function getPbjxServiceLocator()
    {
        return $this->getContainer()->get('gdbots_pbjx.service_locator');
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        if ($this->getContainer()->has('monolog.logger.pbjx')) {
            return $this->getContainer()->get('monolog.logger.pbjx');
        }

        return $this->getContainer()->get('logger');
    }

    /**
     * Some pbjx binders, validators, etc. expect a request to exist.  Create one
     * if nothing has been created yet.
     */
    protected function createConsoleRequest()
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->getContainer()->get('request_stack');
        $request = $requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            $request = new Request();
            $requestStack->push($request);
        }

        $request->attributes->set('pbjx_console', true);
        $request->attributes->set('pbjx_bind_unrestricted', true);
    }
}
