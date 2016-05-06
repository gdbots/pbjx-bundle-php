<?php

namespace Gdbots\Bundle\PbjxBundle\Command;

use Aws\DynamoDb\DynamoDbClient;
use Gdbots\Pbjx\EventStore\DynamoDbEventStoreTable;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateDynamoDbEventStoreCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pbjx:create-dynamodb-event-store')
            ->setDescription('Creates a DynamoDb table for the event store.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will create a table for the event store.

If the table already exists it will NOT modify it. 

EOF
            )
            ->addOption(
                'table-name',
                null,
                InputOption::VALUE_REQUIRED,
                'The DynamoDb table name to create.',
                DynamoDbEventStoreTable::DEFAULT_NAME
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
        $container = $this->getContainer();
        /** @var DynamoDbClient $client */
        $client = $container->get('aws.dynamodb');
        $name = $input->getOption('table-name');

        $table = new DynamoDbEventStoreTable($client, $name);
        $table->create();
        $output->writeln($table->describe());
    }
}
