<?php
declare(strict_types=1);

namespace Gdbots\Tests\Bundle\PbjxBundle\DependencyInjection\Compiler;

use Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler\ValidateTransportsPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ValidateTransportsPassTest extends TestCase
{
    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage The "gdbots_pbjx.command_bus.transport" is configured to use "gearman" which requires service "gdbots_pbjx.transport.gearman".
     */
    public function testValidateGearmanNotDefinedTransport()
    {
        $container = new ContainerBuilder();
        $container->setParameter('gdbots_pbjx.command_bus.transport', 'gearman');

        $this->process($container);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage The service "gdbots_pbjx.transport.gearman" requires "gdbots_pbjx.transport.gearman.servers" parameter to have at least 1 element(s) defined.
     */
    public function testValidateGearmanTransportNoServers()
    {
        $container = new ContainerBuilder();
        $container->setParameter('gdbots_pbjx.command_bus.transport', 'gearman');
        $container->setParameter('gdbots_pbjx.transport.gearman.servers', []);
        $container->setParameter('gdbots_pbjx.transport.gearman.timeout', 5000);

        $container
            ->register('gdbots_pbjx.transport.gearman')
            ->addArgument(new Definition('gdbots_pbjx.service_locator'))
            ->addArgument($container->getParameter('gdbots_pbjx.transport.gearman.servers'))
            ->addArgument($container->getParameter('gdbots_pbjx.transport.gearman.timeout'))
            ->addArgument(new Definition('gdbots_pbjx.transport.gearman_router'));

        $this->process($container);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage The service "gdbots_pbjx.transport.kinesis" has a dependency on a non-existent service "gdbots_pbjx.transport.kinesis_router". You must define this in your app since it requires stream names and partition logic. See \Gdbots\Pbjx\PartitionableRouter.
     */
    public function testValidateKinesisTransport()
    {
        $container = new ContainerBuilder();
        $container->setParameter('gdbots_pbjx.command_bus.transport', 'kinesis');

        $container
            ->register('gdbots_pbjx.transport.kinesis')
            ->addArgument(new Definition('gdbots_pbjx.service_locator'));

        $this->process($container);
    }


    protected function process(ContainerBuilder $container)
    {
        $pass = new ValidateTransportsPass();
        $pass->process($container);
    }
}
