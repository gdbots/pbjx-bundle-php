<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class CreateSchedulerCommand extends Command
{
    use PbjxAwareCommandTrait;

    protected static $defaultName = 'pbjx:create-scheduler';

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
    }

    protected function configure()
    {
        $provider = $this->container->getParameter('gdbots_pbjx.scheduler.provider');
        $this->setDescription("Creates the Scheduler ({$provider}) storage");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Scheduler Storage Creator');
        $this->getPbjxServiceLocator()->getScheduler()->createStorage();
        $io->success('Scheduler storage created.');

        return self::SUCCESS;
    }
}
