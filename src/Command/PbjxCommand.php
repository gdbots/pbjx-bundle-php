<?php

namespace Gdbots\Bundle\PbjxBundle\Command;

use Eme\Schemas\Solicits\Command\RespondToSolicitV1;
use Eme\Schemas\Solicits\SolicitId;
use Gdbots\Pbjx\Pbjx;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class PbjxCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('pbjx');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('test');
        $stdErr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        $pbjx = $this->getPbjx();
        $command = RespondToSolicitV1::create()->set('solicit_id', SolicitId::generate());

        $request = Request::createFromGlobals();
        $requestStack = $this->getRequestStack();
        $requestStack->push($request);
        $request = $requestStack->getCurrentRequest();
        if ($request instanceof Request) {
            $output->writeln(json_encode($request->attributes->all(), JSON_PRETTY_PRINT));
        }

        //$stdErr->writeln('taco');
        $pbjx->send($command);
    }

    /**
     * @return Pbjx
     */
    private function getPbjx()
    {
        return $this->getContainer()->get('pbjx');
    }

    /**
     * @return RequestStack
     */
    private function getRequestStack()
    {
        return $this->getContainer()->get('request_stack');
    }
}
