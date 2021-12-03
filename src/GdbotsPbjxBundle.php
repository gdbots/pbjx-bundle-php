<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler\RegisterHandlersPass;
use Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler\ValidateEventSearchPass;
use Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler\ValidateEventStorePass;
use Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler\ValidateSchedulerPass;
use Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler\ValidateTransportsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class GdbotsPbjxBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new ValidateTransportsPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $container->addCompilerPass(new ValidateEventSearchPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $container->addCompilerPass(new ValidateEventStorePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $container->addCompilerPass(new ValidateSchedulerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        /*
         * Fixme: remove this if approved.
         * Since symfony/event-dispatcher 5.3:
         * Configuring "Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass" is deprecated.
         * $container->addCompilerPass(
         *   new RegisterListenersPass(
         *       'gdbots_pbjx.event_dispatcher',
         *       'pbjx.event_listener',
         *       'pbjx.event_subscriber'
         *  ),
         *   PassConfig::TYPE_BEFORE_REMOVING
         * );
         */
        $container->addCompilerPass(new RegisterHandlersPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }
}
