<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class CreateEventStoreStorageCommand extends ContainerAwareCommand
{
    use PbjxAwareCommandTrait;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pbjx:create-event-store-storage')
            ->setDescription('Creates the EventStore storage.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will create the storage for the EventStore.  

<info>php %command.full_name% --tenant-id=client1</info>

EOF
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
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return null
     *
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $context = $input->getOption('context') ?: '{}';
        if (strpos($context, '{') === false) {
            $context = base64_decode($context);
        }
        $context = json_decode($context, true);
        $context['tenant_id'] = (string)$input->getOption('tenant-id');

        $io = new SymfonyStyle($input, $output);
        $io->title('EventStore Storage Creator');
        $io->comment(sprintf('context: %s', json_encode($context)));

        $this->getPbjx()->getEventStore()->createStorage($context);
        $io->success(sprintf('EventStore storage created.'));
    }
}
