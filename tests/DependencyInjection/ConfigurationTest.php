<?php
declare(strict_types=1);

namespace Gdbots\Tests\Bundle\PbjxBundle\DependencyInjection;

use Gdbots\Bundle\PbjxBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfig()
    {
        $this->markTestSkipped();

        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [['transport' => []]]);

        $this->assertEquals(
            array_merge(['transport' => []], self::getBundleDefaultConfig()),
            $config
        );
    }

    public function testDefaultTransportGearmanConfig()
    {
        $this->markTestSkipped();

        $options = [
            'transport' => [
                'gearman' => [
                    'servers'        => [
                        [
                            'host' => '127.0.0.1',
                            'port' => 4730,
                        ],
                    ],
                    'timeout'        => 5000,
                    'channel_prefix' => null,
                ],
            ],
        ];

        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [$options]);

        $this->assertEquals(
            array_merge($options, self::getBundleDefaultConfig()),
            $config
        );
    }

    protected static function getBundleDefaultConfig()
    {
        return [
            'service_locator'         => [
                'class' => 'Gdbots\Bundle\PbjxBundle\ContainerAwareServiceLocator',
            ],
            'pbjx_controller'         => [
                'allow_get_request' => false,
            ],
            'pbjx_receive_controller' => [
                'enabled'     => false,
                'receive_key' => null,
            ],
            'handler_guesser'         => [
                'class' => 'Gdbots\Bundle\PbjxBundle\HandlerGuesser',
            ],
            'command_bus'             => [
                'transport' => 'in_memory',
            ],
            'event_bus'               => [
                'transport' => 'in_memory',
            ],
            'request_bus'             => [
                'transport' => 'in_memory',
            ],
            'event_store'             => [
                'provider' => null,
            ],
            'event_search'            => [
                'provider' => null,
            ],
        ];
    }
}
