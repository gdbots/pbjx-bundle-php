<?php

namespace Gdbots\Tests\Bundle\PbjxBundle\DependencyInjection\Compiler;

use Gdbots\Bundle\PbjxBundle\DependencyInjection\Compiler\ValidateTransportsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ValidateTransportsPassTest extends \PHPUnit_Framework_TestCase
{
    /** @var ValidateTransportsPass */
    private $validate;

    /** @var ContainerBuilder */
    private $container;

    protected function setUp()
    {
        parent::setUp();

        $this->validate = new ValidateTransportsPass();
        $this->container = new ContainerBuilder();
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->validate = null;
        $this->container = null;
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage The "gdbots_pbjx.command_bus.transport" is configured to use "gearman" which requires service "gdbots_pbjx.transport.gearman".
     */
    public function testValidateGearmanTransport()
    {
        $this->container->setParameter('gdbots_pbjx.command_bus.transport', 'gearman');
        $this->validate->process($this->container);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage The "gdbots_pbjx.command_bus.transport" is configured to use "gearman" which requires service "gdbots_pbjx.transport.gearman".
     */
    public function testValidateGearmanTransportNoServers()
    {
        $this->container->setParameter('gdbots_pbjx.command_bus.transport', 'gearman');
        $this->container->setParameter('gdbots_pbjx.transport.gearman.servers', []);
        $this->validate->process($this->container);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage The "gdbots_pbjx.command_bus.transport" is configured to use "kinesis" which requires service "gdbots_pbjx.transport.kinesis".
     */
    public function testValidateKinesisTransport()
    {
        $this->container->setParameter('gdbots_pbjx.command_bus.transport', 'kinesis');
        $this->validate->process($this->container);
    }
}
