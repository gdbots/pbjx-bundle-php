<?php

namespace Gdbots\Tests\Bundle\PbjxBundle\Controller;

use Gdbots\Bundle\PbjxBundle\Controller\PbjxReceiveController;
use Gdbots\Pbj\Message;
use Gdbots\Pbjx\RegisteringServiceLocator;
use Gdbots\Pbjx\Transport\TransportEnvelope;
use Gdbots\Tests\Bundle\PbjxBundle\Fixtures\FakeCommand;
use Symfony\Component\HttpFoundation\Request;

class PbjxReceiveControllerTest extends \PHPUnit_Framework_TestCase
{
    public function testValidReceive()
    {
        $locator = new RegisteringServiceLocator();
        $controller = new PbjxReceiveController($locator, 'test', true);

        $messages = [
            FakeCommand::create(),
            FakeCommand::create(),
            FakeCommand::create(),
        ];

        $lines = implode(PHP_EOL, array_map(function (Message $message) {
            return (new TransportEnvelope($message, 'json'))->toString();
        }, $messages));

        $request = new Request([], [], [], [], [], ['HTTP_x-pbjx-receive-key' => 'test'], $lines);
        $request->setMethod('POST');
        $response = $controller->receiveAction($request);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('lines', $data);
        $this->assertArrayHasKey('total', $data['lines']);

        $this->assertSame(3, $data['lines']['total']);
        $this->assertSame(3, $data['lines']['ok']);

        foreach ($data['results'] as $k => $result) {
            $this->assertSame((string)$messages[$k]->generateMessageRef(), $result['message_ref']);
        }
    }

    public function testInvalidReceive()
    {
        $locator = new RegisteringServiceLocator();
        $controller = new PbjxReceiveController($locator, 'test', true);

        $messages = [
            FakeCommand::create(),
            FakeCommand::create(),
            FakeCommand::create(),
        ];

        $lines = implode(PHP_EOL, array_map(function (Message $message) {
            return (new TransportEnvelope($message, 'json'))->toString();
        }, $messages));

        $lines .= PHP_EOL . 'invalid json';
        $lines .= PHP_EOL.PHP_EOL;

        $request = new Request([], [], [], [], [], ['HTTP_x-pbjx-receive-key' => 'test'], $lines);
        $request->setMethod('POST');
        $response = $controller->receiveAction($request);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('lines', $data);
        $this->assertArrayHasKey('total', $data['lines']);

        $this->assertSame(5, $data['lines']['total']);
        $this->assertSame(3, $data['lines']['ok']);
        $this->assertSame(1, $data['lines']['failed']);
        $this->assertSame(1, $data['lines']['ignored']);

        foreach ($data['results'] as $k => $result) {
            if ($result['ok']) {
                $this->assertSame((string)$messages[$k]->generateMessageRef(), $result['message_ref']);
            } else {
                $this->assertSame(3, $result['code']);
            }
        }
    }
}