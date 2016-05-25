<?php

namespace Gdbots\Bundle\PbjxBundle\Command;

use Aws\DynamoDb\DynamoDbClient;
use Gdbots\Pbjx\EventStore\DynamoDbEventStoreTable;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
The <info>%command.name%</info> command will create a table for the event store using the 
"Gdbots\Pbjx\EventStore\DynamoDbEventStoreTable" class.

<info>If the table already exists it will NOT modify it.</info>

EOF
            )
            ->addArgument(
                'table-name',
                InputArgument::OPTIONAL,
                'The DynamoDb table name to create, if not provided the "gdbots_pbjx.event_store.dynamodb.table_name" parameter will be used.'
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
        $name = $input->getArgument('table-name') ?: $container->getParameter('gdbots_pbjx.event_store.dynamodb.table_name');

        $io = new SymfonyStyle($input, $output);
        $io->title('DynamoDb Event Store Creator ' . DynamoDbEventStoreTable::SCHEMA_VERSION);

        $io->text(sprintf(
            'Creating DynamoDb table "%s" in region "%s", this might take a few minutes.', $name, $client->getRegion()
        ));

        $table = new DynamoDbEventStoreTable($client, $name);
        $table->create();
        $io->success(sprintf('DynamoDb table "%s" in region "%s" created.', $name, $client->getRegion()));
        $io->comment('json output of DynamoDbClient::describeTable');
        $io->text($table->describe());
    }
}
