<?php
declare(strict_types=1);

namespace Gdbots\Tests\Bundle\PbjxBundle\DependencyInjection\Compiler;

use Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler\ValidateTransportsPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ValidateTransportsPassTest extends TestCase
{
    public function testValidateKinesisTransport(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectErrorMessage('The service "gdbots_pbjx.transport.kinesis" has a dependency on a non-existent service "gdbots_pbjx.transport.kinesis_router". You must define this in your app since it requires stream names and partition logic. See \Gdbots\Pbjx\PartitionableRouter.');
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
