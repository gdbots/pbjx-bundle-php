<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[AsCommand(name: 'pbjx:describe-event-store-storage')]
final class DescribeEventStoreStorageCommand extends Command
{
    use PbjxAwareCommandTrait;

    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $provider = $this->container->getParameter('gdbots_pbjx.event_store.provider');

        $this
            ->setDescription("Describes the EventStore ({$provider}) storage")
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = $input->getOption('context') ?: '{}';
        if (!str_contains($context, '{')) {
            $context = base64_decode($context);
        }
        $context = json_decode($context, true);
        $context['tenant_id'] = (string)$input->getOption('tenant-id');

        $io = new SymfonyStyle($input, $output);
        $io->title('EventStore Storage Describer');
        $io->comment('context: ' . json_encode($context));

        $details = $this->getPbjx()->getEventStore()->describeStorage($context);
        $io->text($details);
        $io->newLine();

        return self::SUCCESS;
    }
}
