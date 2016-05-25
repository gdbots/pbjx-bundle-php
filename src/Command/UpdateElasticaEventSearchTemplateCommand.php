<?php

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Pbjx\EventSearch\ElasticaClientManager;
use Gdbots\Pbjx\EventSearch\ElasticaIndexManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateElasticaEventSearchTemplateCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pbjx:update-elastica-event-search-template')
            ->setDescription('Updates the event search index templates in elastic search.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will update (or create if it doesn't exist) an index template 
in elastic search using the "elastica" library.  The index template contains the settings and mappings 
for all indexed events, which means this should be run whenever an event schema changes.

<comment>If you need to update existing indices that have already been created using an old template or no
template at all... run this command "pbjx:update-elastica-event-search-indices".</comment>

The template name will be the value of parameter <info>"gdbots_pbjx.event_search.elastica.index_manager.index_prefix"</info>
and the template pattern will be its value wrapped with wildcards "*prod-events*".  This allows for interval 
suffix to match and any potential prefixes added for multi-tenant apps, e.g. <comment>client1-dev-events-2015q1</comment> 

<info>php %command.full_name% --cluster=client123</info>

EOF
            )
            ->addOption(
                'cluster',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'The clusters to create the template in.  If not supplied, the template is created in all clusters.'
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
        $io->title('Elastica Event Search Index Template Creator');

        $template = $container->getParameter('gdbots_pbjx.event_search.elastica.index_manager.index_prefix');
        $clusters = $input->getOption('cluster') ? (array)$input->getOption('cluster') : $clientManager->getAvailableClusters();

        foreach ($clusters as $cluster) {
            $io->text(sprintf(
                'Creating Elastic Search index template "%s" in cluster "%s", this might take a few minutes.',
                $template,
                $cluster
            ));

            $indexManager->updateTemplate($clientManager->getClient($cluster), $template);
            $io->success(sprintf('Created Elastic Search index template "%s" in cluster "%s"', $template, $cluster));
        }
    }
}
