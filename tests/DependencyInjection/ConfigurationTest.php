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
        $config = $processor->processConfiguration(new Configuration(), [self::getBundleDefaultConfig()]);
//
//        echo json_encode($config, JSON_PRETTY_PRINT);
//        exit;
    }

    public function testDefaultTransportGearmanConfig()
    {
        $this->markTestSkipped();

        $configs = array_merge(self::getBundleDefaultConfig(), [
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
        ]);

        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), $configs);
    }

    protected static function getBundleDefaultConfig()
    {
        return [
            'service_locator'         => [
                'class' => 'Gdbots\Bundle\PbjxBundle\ContainerAwareServiceLocator',
            ],
            'pbjx_token_signer'       => [
                'default_kid' => 'kid',
                'keys'        => [
                    ['kid' => 'kid1', 'secret' => 'secret1'],
                    ['kid' => 'kid2', 'secret' => 'secret2'],
                ],
            ],
            'pbjx_controller'         => [
                'allow_get_request' => false,
            ],
            'pbjx_receive_controller' => [
                'enabled' => false,
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
