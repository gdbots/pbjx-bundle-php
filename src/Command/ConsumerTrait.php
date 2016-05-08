<?php

namespace Gdbots\Bundle\PbjxBundle\Command;

use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\ServiceLocator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @method ContainerInterface getContainer()
 */
trait ConsumerTrait
{
    /**
     * @return Pbjx
     */
    protected function getPbjx()
    {
        return $this->getContainer()->get('pbjx');
    }

    /**
     * @return ServiceLocator
     */
    protected function getPbjxServiceLocator()
    {
        return $this->getContainer()->get('gdbots_pbjx.service_locator');
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        if ($this->getContainer()->has('monolog.logger.pbjx')) {
            return $this->getContainer()->get('monolog.logger.pbjx');
        }

        return $this->getContainer()->get('logger');
    }

    /**
     * Some pbjx binders, validators, etc. expect a request to exist.  Create one
     * if nothing has been created yet.
     */
    protected function createConsoleRequest()
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->getContainer()->get('request_stack');
        $request = $requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            $request = new Request();
            $requestStack->push($request);
        }

        $request->attributes->set('pbjx_console', true);
        $request->attributes->set('pbjx_bind_unrestricted', true);
    }
}
