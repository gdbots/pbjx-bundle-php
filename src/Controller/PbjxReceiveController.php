<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Controller;

use Gdbots\Bundle\PbjxBundle\PbjxTokenSigner;
use Gdbots\Pbj\Exception\GdbotsPbjException;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\Util\ClassUtil;
use Gdbots\Pbjx\Exception\RequestHandlingFailed;
use Gdbots\Pbjx\ServiceLocator;
use Gdbots\Pbjx\Transport\TransportEnvelope;
use Gdbots\Pbjx\Util\StatusCodeUtil;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Enum\HttpCode;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * This endpoint receives a transport envelope which is directly
 * processed through the associated pbjx transport bus (command/event).
 *
 * A @see PbjxToken MUST be provided in the "x-pbjx-token" header
 * to ensure the security of this endpoint.
 *
 * This endpoint is ideally secured in a VPC and only called by
 * internal services.
 *
 */
final class PbjxReceiveController
{
    private ServiceLocator $locator;
    private PbjxTokenSigner $signer;
    private bool $enabled;

    public function __construct(ServiceLocator $locator, PbjxTokenSigner $signer, bool $enabled = false)
    {
        $this->locator = $locator;
        $this->signer = $signer;
        $this->enabled = $enabled;
    }

    public function receiveAction(Request $request): JsonResponse
    {
        if (!$this->enabled) {
            throw new AccessDeniedHttpException(
                'The receive endpoint is not enabled.',
                null,
                Code::UNIMPLEMENTED->value
            );
        }

        $token = $request->headers->get('x-pbjx-token');
        if (empty($token)) {
            throw new AccessDeniedHttpException(
                'The receive endpoint requires the "x-pbjx-token" header.',
                null,
                Code::PERMISSION_DENIED->value
            );
        }

        try {
            $this->signer->validate($request->getContent(), $request->getUri(), $token);
        } catch (\Throwable $e) {
            throw new AccessDeniedHttpException($e->getMessage(), $e, Code::PERMISSION_DENIED->value);
        }

        $handle = $request->getContent(true);
        $data = [
            'lines'   => [
                'total'   => 0,
                'ok'      => 0,
                'failed'  => 0,
                'ignored' => 0,
            ],
            'results' => [],
        ];

        while (($line = fgets($handle)) !== false) {
            ++$data['lines']['total'];

            $line = trim($line);
            if (empty($line)) {
                ++$data['lines']['ignored'];
                $data['results'][] = [
                    'ok'            => false,
                    'code'          => Code::INVALID_ARGUMENT->value,
                    'error_name'    => 'InvalidArgumentException',
                    'error_message' => 'empty line',
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
                $result['code'] = Code::OK->value;
                $result['message_ref'] = $message->generateMessageRef()->toString();
            } catch (\Throwable $e) {
                ++$data['lines']['failed'];
                $this->handleException($result, $e);

                if ($message instanceof Message) {
                    $result['message_ref'] = $message->generateMessageRef()->toString();
                }
            }

            $data['results'][] = $result;
        }

        return new JsonResponse($data);
    }

    private function receiveMessage(Message $message): void
    {
        if ($message::schema()->hasMixin('gdbots:pbjx:mixin:command')) {
            $this->locator->getCommandBus()->receiveCommand($message);
            return;
        }

        if ($message::schema()->hasMixin('gdbots:pbjx:mixin:event')) {
            $this->locator->getEventBus()->receiveEvent($message);
            return;
        }

        throw new BadRequestHttpException(
            'The receive endpoint cannot process requests.',
            null,
            Code::INVALID_ARGUMENT->value
        );
    }

    private function handleException(array &$result, \Throwable $exception): void
    {
        if ($exception instanceof HttpExceptionInterface) {
            $httpCode = HttpCode::tryFrom($exception->getStatusCode()) ?: HttpCode::UNKNOWN;
            $code = StatusCodeUtil::httpToVendor($httpCode);
            $errorName = ClassUtil::getShortName($exception);
            $errorMessage = $exception->getMessage();
        } elseif ($exception instanceof RequestHandlingFailed) {
            $response = $exception->getResponse();
            $code = Code::tryFrom($response->get('error_code')) ?: Code::UNKNOWN;
            $errorName = $response->get('error_name', ClassUtil::getShortName($exception));
            $errorMessage = $response->get('error_message', $exception->getMessage());
        } elseif ($exception instanceof GdbotsPbjException) {
            $code = Code::INVALID_ARGUMENT;
            $errorName = ClassUtil::getShortName($exception);
            $errorMessage = $exception->getMessage();
        } else {
            $code = Code::tryFrom(
                $exception->getCode() > 0 ? $exception->getCode() : Code::INVALID_ARGUMENT->value
            ) ?: Code::INVALID_ARGUMENT;
            $errorName = ClassUtil::getShortName($exception);
            $errorMessage = $exception->getMessage();
        }

        $result['ok'] = false;
        $result['code'] = $code->value;
        $result['error_name'] = $errorName;
        $result['error_message'] = $errorMessage;
    }
}
