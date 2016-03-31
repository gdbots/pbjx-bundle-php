<?php

namespace Gdbots\Tests\Bundle\PbjxBundle\DependencyInjection;

use Gdbots\Bundle\PbjxBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaultConfig()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [['transport' => []]]);

        $this->assertEquals(
            array_merge(['transport' => []], self::getBundleDefaultConfig()),
            $config
        );
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testInvalidValueEnum()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $processor->processConfiguration($configuration, [
            [
                'command_bus' => [
                    'transport' => 'invalid value'
                ],
            ],
        ]);
    }

    public function testDefaultTransportGearmanConfig()
    {
        $options = [
            'transport' => [
                'gearman' => [
                    'servers' => [
                        [
                            'host' => '127.0.0.1',
                            'port' => 4730
                        ]
                    ],
                    'timeout' => 5000,
                    'channel_prefix' => null
                ]
            ]
        ];

        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [$options]);

        $this->assertEquals(
            array_merge($options, self::getBundleDefaultConfig()),
            $config
        );
    }

    protected static function getBundleDefaultConfig()
    {
        return [
            'pbjx_controller' => [
                'allow_get_request' => false
            ],
            'command_bus' => [
                'transport' => 'in_memory'
            ],
            'event_bus' => [
                'transport' => 'in_memory'
            ],
            'request_bus' => [
                'transport' => 'in_memory'
            ]
        ];
    }
}
