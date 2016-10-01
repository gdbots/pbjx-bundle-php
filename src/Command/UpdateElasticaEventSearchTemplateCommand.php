<?php

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Pbjx\EventSearch\ElasticaClientManager;
use Gdbots\Pbjx\EventSearch\ElasticaIndexManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
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
            ->setDescription('Updates the event search index template in elastic search.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will update (or create if it doesn't exist) an index template 
in elastic search using the "elastica" library.  The index template contains the settings and mappings 
for all indexed events, which means this should be run whenever an event schema changes.

<comment>If you need to update existing indices that have already been created using an old template or no
template at all... run this command "pbjx:update-elastica-event-search-indices".</comment>

The template pattern will be the name wrapped with wildcards "*prod-events*".  This allows for interval 
suffix to match and any potential prefixes added for multi-tenant apps, e.g. <comment>client1-dev-events-2015q1</comment> 

<info>php %command.full_name% --cluster=client123</info>

EOF
            )
            ->addOption(
                'cluster',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'The clusters to put the template into.  If not supplied, the template is put into all clusters.'
            )
            ->addArgument(
                'template',
                InputArgument::OPTIONAL,
                'The template name to use, if not provided the "gdbots_pbjx.event_search.elastica.index_manager.index_prefix" parameter will be used.'
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
        $io->title('Elastica Event Search Index Template Updater');

        $template = $input->getArgument('template') ?: $container->getParameter('gdbots_pbjx.event_search.elastica.index_manager.index_prefix');
        $clusters = $input->getOption('cluster') ?: $clientManager->getAvailableClusters();

        foreach ($clusters as $cluster) {
            $io->text(sprintf('Putting Elastic Search index template "%s" into cluster "%s".', $template, $cluster));
            $indexManager->updateTemplate($clientManager->getClient($cluster), $template);
            $io->success(sprintf('Updated Elastic Search index template "%s" in cluster "%s"', $template, $cluster));
        }
    }
}
