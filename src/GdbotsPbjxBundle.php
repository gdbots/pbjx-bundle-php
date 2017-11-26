<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle;

use Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler\AliasHandlersPass;
use Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler\RegisterListenersPass;
use Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler\ValidateEventSearchPass;
use Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler\ValidateEventStorePass;
use Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler\ValidateTransportsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class GdbotsPbjxBundle extends Bundle
{
    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new ValidateTransportsPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $container->addCompilerPass(new ValidateEventSearchPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $container->addCompilerPass(new ValidateEventStorePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $container->addCompilerPass(new RegisterListenersPass(), PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new AliasHandlersPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }
}
