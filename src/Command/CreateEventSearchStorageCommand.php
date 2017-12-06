<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class CreateEventSearchStorageCommand extends ContainerAwareCommand
{
    use PbjxAwareCommandTrait;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct(null);
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pbjx:create-event-search-storage')
            ->setDescription('Creates the EventSearch storage.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will create the storage for the EventSearch.  

<info>php %command.full_name% --tenant-id=client1</info>

EOF
            )
            ->addOption(
                'context',
                null,
                InputOption::VALUE_REQUIRED,
                'Context to provide to the EventSearch (json).'
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
        $context = json_decode($input->getOption('context') ?: '{}', true);
        $context['tenant_id'] = (string)$input->getOption('tenant-id');

        $io = new SymfonyStyle($input, $output);
        $io->title('EventSearch Storage Creator');
        $io->comment(sprintf('context: %s', json_encode($context)));

        $this->getPbjx()->getEventSearch()->createStorage($context);
        $io->success(sprintf('EventSearch storage created.'));
    }
}
