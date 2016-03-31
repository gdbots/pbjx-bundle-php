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
        $extension->load(array(array()), $this->container);

        $this->assertFalse($this->container->has('pbjx_controller.allow_get_request'));
    }
}
