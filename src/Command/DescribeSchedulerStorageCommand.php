<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class DescribeSchedulerStorageCommand extends Command
{
    use PbjxAwareCommandTrait;

    protected static $defaultName = 'pbjx:describe-scheduler-storage';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Scheduler Storage Describer');

        $details = $this->getPbjxServiceLocator()->getScheduler()->describeStorage();
        $io->text($details);
        $io->newLine();

        return self::SUCCESS;
    }
}
