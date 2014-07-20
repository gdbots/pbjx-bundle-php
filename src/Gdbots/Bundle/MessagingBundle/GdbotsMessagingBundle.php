<?php

namespace Gdbots\Bundle\MessagingBundle;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class GdbotsMessagingBundle extends Bundle
{
    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterListenersPass(
                'gdbots_messaging.dispatcher',
                'gdbots_messaging.lifecycle_event_listener',
                'gdbots_messaging.lifecycle_event_subscriber'
            ), PassConfig::TYPE_BEFORE_REMOVING);
    }
}
