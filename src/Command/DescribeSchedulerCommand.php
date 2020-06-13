<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class DescribeSchedulerCommand extends Command
{
    use PbjxAwareCommandTrait;

    protected static $defaultName = 'pbjx:describe-scheduler';

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
    }

    protected function configure()
    {
        $provider = $this->container->getParameter('gdbots_pbjx.scheduler.provider');
        $this->setDescription("Describes the Scheduler ({$provider}) storage");
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
