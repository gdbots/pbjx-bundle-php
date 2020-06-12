<?php
declare(strict_types=1);

namespace Gdbots\Tests\Bundle\PbjxBundle\Controller;

use Gdbots\Bundle\PbjxBundle\Controller\PbjxReceiveController;
use Gdbots\Bundle\PbjxBundle\PbjxTokenSigner;
use Gdbots\Pbj\Message;
use Gdbots\Pbjx\RegisteringServiceLocator;
use Gdbots\Pbjx\Transport\TransportEnvelope;
use Gdbots\Schemas\Pbjx\Command\CheckHealthV1;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class PbjxReceiveControllerTest extends TestCase
{
    public function testValidReceive(): void
    {
        $locator = new RegisteringServiceLocator();
        $signer = new PbjxTokenSigner([['kid' => 'kid', 'secret' => 'secret']]);
        $controller = new PbjxReceiveController($locator, $signer, true);

        $messages = [
            CheckHealthV1::create(),
            CheckHealthV1::create(),
            CheckHealthV1::create(),
        ];

        $lines = implode(PHP_EOL, array_map(function (Message $message) {
            return (new TransportEnvelope($message, 'json'))->toString();
        }, $messages));

        $token = $signer->sign($lines, 'http://:/');
        $request = new Request([], [], [], [], [], ['HTTP_x-pbjx-token' => $token->toString()], $lines);
        $request->setMethod('POST');
        $response = $controller->receiveAction($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('lines', $data);
        $this->assertArrayHasKey('total', $data['lines']);

        $this->assertSame(3, $data['lines']['total']);
        $this->assertSame(3, $data['lines']['ok']);

        foreach ($data['results'] as $k => $result) {
            $this->assertSame((string)$messages[$k]->generateMessageRef(), $result['message_ref']);
        }
    }

    public function testInvalidReceive(): void
    {
        $locator = new RegisteringServiceLocator();
        $signer = new PbjxTokenSigner([['kid' => 'kid', 'secret' => 'secret']]);
        $controller = new PbjxReceiveController($locator, $signer, true);

        $messages = [
            CheckHealthV1::create(),
            CheckHealthV1::create(),
            CheckHealthV1::create(),
        ];

        $lines = implode(PHP_EOL, array_map(function (Message $message) {
            return (new TransportEnvelope($message, 'json'))->toString();
        }, $messages));

        $lines .= PHP_EOL . 'invalid json';
        $lines .= PHP_EOL . PHP_EOL;

        $token = $signer->sign($lines, 'http://:/');
        $request = new Request([], [], [], [], [], ['HTTP_x-pbjx-token' => $token->toString()], $lines);
        $request->setMethod('POST');
        $response = $controller->receiveAction($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
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
