<?php

namespace Gdbots\Tests\Bundle\PbjxBundle\DependencyInjection;

use Gdbots\Bundle\PbjxBundle\DependencyInjection\GdbotsPbjxExtension;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class GdbotsPbjxExtensionTest extends \PHPUnit_Framework_TestCase
{
    /** @var \Symfony\Component\DependencyInjection\Container */
    private $container;

    protected function setUp()
    {
        parent::setUp();

        $this->container = new ContainerBuilder();
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->container = null;
    }

    public function testDefaultConfig()
    {
        $extension = new GdbotsPbjxExtension();
        $extension->load([[]], $this->container);

        $this->assertFalse($this->container->has('pbjx_controller.allow_get_request'));
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage The service "gdbots_pbjx.transport.gearman" has a dependency on a non-existent parameter "gdbots_pbjx.transport.gearman.servers".
     */
    public function testValidateGearmanTransport()
    {
        $extension = new GdbotsPbjxExtension();
        $extension->load([['command_bus' => ['transport' => 'gearman']]], $this->container);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage The path "gdbots_pbjx.transport.gearman.servers" should have at least 1 element(s) defined.
     */
    public function testValidateGearmanTransportNoServers()
    {
        $extension = new GdbotsPbjxExtension();
        $extension->load([[
            'command_bus' => ['transport' => 'gearman'],
            'transport' => [
                'gearman' => [
                    'servers' => []
                ]
            ]
        ]], $this->container);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage The service "gdbots_pbjx.transport.kinesis" has a dependency on a non-existent service "gdbots_pbjx.transport.kinesis_router".
     */
    public function testValidateKinesisTransport()
    {
        $extension = new GdbotsPbjxExtension();
        $extension->load([['command_bus' => ['transport' => 'kinesis']]], $this->container);
    }
}
