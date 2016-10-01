<?php

namespace Gdbots\Bundle\PbjxBundle\Controller;

use Gdbots\Bundle\PbjxBundle\Util\StatusCodeConverter;
use Gdbots\Common\Util\ClassUtils;
use Gdbots\Pbj\Exception\GdbotsPbjException;
use Gdbots\Pbj\Exception\HasEndUserMessage;
use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Exception\RequestHandlingFailed;
use Gdbots\Pbjx\ServiceLocator;
use Gdbots\Pbjx\Transport\TransportEnvelope;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Intl\Exception\NotImplementedException;

/**
 * An endpoint to receive a transport envelope.  This is typically not used externally as it
 * would be a security hole to allow unauthorized clients to submit their own messages.
 *
 * The 'gdbots_pbjx.pbjx_receive_controller.receive_key' container parameter is used to authorize this endpoint.
 * The key should be provided as header "x-pbjx-receive-key: YOUR_KEY".
 *
 * However, it is still highly recommended to limit the access to this endpoint.
 *
 */
class PbjxReceiveController
{
    /** @var ServiceLocator */
    protected $locator;

    /** @var string */
    protected $receiveKey;

    /** @var bool */
    protected $enabled = false;

    /**
     * @param ServiceLocator $locator
     * @param string $receiveKey
     * @param bool $enabled
     */
    public function __construct(ServiceLocator $locator, $receiveKey, $enabled = false)
    {
        $this->locator = $locator;
        $this->receiveKey = trim($receiveKey);
        $this->enabled = $enabled;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function receiveAction(Request $request)
    {
        if (false === $this->enabled) {
            throw new AccessDeniedHttpException('The receive endpoint is not enabled.', null, Code::PERMISSION_DENIED);
        }

        if (empty($this->receiveKey) || $this->receiveKey !== $request->headers->get('x-pbjx-receive-key')) {
            throw new AccessDeniedHttpException('Receive key is not valid.', null, Code::UNAUTHENTICATED);
        }

        $handle = $request->getContent(true);
        $data = [
            'lines' => [
                'total' => 0,
                'ok' => 0,
                'failed' => 0,
                'ignored' => 0,
            ],
            'results' => []
        ];

        while (($line = fgets($handle)) !== false) {
            ++$data['lines']['total'];

            $line = trim($line);
            if (empty($line)) {
                ++$data['lines']['ignored'];
                $data['results'][] = [
                    'ok' => false,
                    'code' => Code::INVALID_ARGUMENT,
                    'error_name' => 'InvalidArgumentException',
                    'error_message' => 'empty line'
                ];
                continue;
            }

            $message = null;
            $result = [];

            try {
                $envelope = TransportEnvelope::fromString($line);
                $message = $envelope->getMessage();
                $this->receiveMessage($message);

                ++$data['lines']['ok'];
                $result['ok'] = true;
                $result['code'] = Code::OK;
                $result['message_ref'] = $message->generateMessageRef()->toString();

            } catch (\Exception $e) {
                ++$data['lines']['failed'];
                $this->handleException($result, $e);

                if ($message instanceof Message) {
                    $result['message_ref'] = $message->generateMessageRef()->toString();
                }
            }

            $data['results'][] = $result;
        }

        return JsonResponse::create($data);
    }

    /**
     * @param Message $message
     *
     * @throws NotImplementedException
     */
    protected function receiveMessage(Message $message)
    {
        if ($message instanceof Command) {
            $this->locator->getCommandBus()->receiveCommand($message);
            return;
        }

        if ($message instanceof Event) {
            $this->locator->getEventBus()->receiveEvent($message);
            return;
        }

        throw new BadRequestHttpException('The receive endpoint cannot process requests.', null, Code::INVALID_ARGUMENT);
    }

    /**
     * @param array $result
     * @param \Exception $exception
     */
    protected function handleException(array &$result, \Exception $exception)
    {
        if ($exception instanceof HasEndUserMessage) {
            $code = $exception->getCode();
            $errorName = ClassUtils::getShortName($exception);
            $errorMessage = $exception->getEndUserMessage();

        } elseif ($exception instanceof HttpExceptionInterface) {
            $code = StatusCodeConverter::httpToVendor($exception->getStatusCode());
            $errorName = ClassUtils::getShortName($exception);
            $errorMessage = $exception->getMessage();

        } elseif ($exception instanceof RequestHandlingFailed) {
            $response = $exception->getResponse();
            $code = $response->get('error_code', Code::UNKNOWN);
            $errorName = $response->get('error_name', ClassUtils::getShortName($exception));
            $errorMessage = $response->get('error_message', $exception->getMessage());

        } elseif ($exception instanceof GdbotsPbjException) {
            $code = Code::INVALID_ARGUMENT;
            $errorName = ClassUtils::getShortName($exception);
            $errorMessage = $exception->getMessage();

        } else {
            $code = $exception->getCode() > 0 ? $exception->getCode() : Code::INVALID_ARGUMENT;
            $errorName = ClassUtils::getShortName($exception);
            $errorMessage = $exception->getMessage();
        }

        $result['ok'] = false;
        $result['code'] = $code;
        $result['error_name'] = $errorName;
        $result['error_message'] = $errorMessage;
    }
}
