<?php
declare(strict_types=1);

namespace Gdbots\Tests\Bundle\PbjxBundle\DependencyInjection;

use Gdbots\Bundle\PbjxBundle\DependencyInjection\GdbotsPbjxExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class GdbotsPbjxExtensionTest extends TestCase
{
    /** @var ContainerBuilder */
    private $container;

    protected function setUp()
    {
        parent::setUp();
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.environment', 'dev');
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
}
