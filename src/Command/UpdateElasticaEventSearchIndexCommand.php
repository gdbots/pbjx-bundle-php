<?php

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Pbjx\EventSearch\ElasticaClientManager;
use Gdbots\Pbjx\EventSearch\ElasticaIndexManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateElasticaEventSearchIndexCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pbjx:update-elastica-event-search-index')
            ->setDescription('Updates event search indices in elastic search.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will update all of the mappings and settings for the indices
provided.  This process is handled by the ElasticaIndexManager and will include all events with 
the "gdbots:pbjx:mixin:indexed" mixin.

<error>If any index settings need to be changed, for example analyzers, the index will be closed,</error> 
<error>updated and then reopened by this process, which may take a few minutes. (generally rare)</error>

<info>php %command.full_name% cluster index-2016* index-2015q4</info>

EOF
            )
            ->addArgument('cluster', InputArgument::REQUIRED, 'The cluster to use for the updates.')
            ->addArgument(
                'index',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'The name of the index to update.  Wildcards are supported, i.e. events-2015*'
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
        /** @var ElasticaClientManager $clientManager */
        $clientManager = $container->get('gdbots_pbjx.event_search.elastica.client_manager');
        /** @var ElasticaIndexManager $indexManager */
        $indexManager = $container->get('gdbots_pbjx.event_search.elastica.index_manager');

        $io = new SymfonyStyle($input, $output);
        $io->title('Elastica Event Search Index Updater');

        $cluster = $input->getArgument('cluster');
        $indices = $input->getArgument('index');
        $client = $clientManager->getClient($cluster);

        foreach ($indices as $index) {
            $io->text(sprintf(
                'Updating Elastic Search index "%s" in cluster "%s", this might take a few minutes.',
                $index,
                $cluster
            ));

            $indexManager->updateIndex($client, $index);
            $io->success(sprintf('Updated Elastic Search index "%s" in cluster "%s"', $index, $cluster));
        }
    }
}
