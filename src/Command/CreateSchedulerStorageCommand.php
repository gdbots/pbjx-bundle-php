<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class CreateSchedulerStorageCommand extends ContainerAwareCommand
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
            ->setName('pbjx:create-scheduler-storage')
            ->setDescription('Creates the Scheduler storage.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will create the storage for the Scheduler.  

<info>php %command.full_name%</info>

EOF
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
        $io = new SymfonyStyle($input, $output);
        $io->title('Scheduler Storage Creator');

        $this->getPbjxServiceLocator()->getScheduler()->createStorage();
        $io->success(sprintf('Scheduler storage created.'));
    }
}
