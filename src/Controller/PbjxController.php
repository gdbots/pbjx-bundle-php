<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Controller;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Pbj\Exception\GdbotsPbjException;
use Gdbots\Pbj\Exception\HasEndUserMessage;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbj\SchemaId;
use Gdbots\Pbjx\Exception\RequestHandlingFailed;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\Util\StatusCodeConverter;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Enum\HttpCode;
use Gdbots\Schemas\Pbjx\Envelope;
use Gdbots\Schemas\Pbjx\EnvelopeV1;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request as PbjxRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class PbjxController
{
    /** @var Pbjx */
    private $pbjx;

    /** @var string[] */
    private $allowedMethods = ['POST'];

    /**
     * @param Pbjx $pbjx
     * @param bool $allowGetRequest
     */
    public function __construct(Pbjx $pbjx, bool $allowGetRequest = false)
    {
        $this->pbjx = $pbjx;
        if ($allowGetRequest) {
            $this->allowedMethods = ['GET', 'POST'];
        }
    }

    /**
     * @param Request $request
     *
     * @return Envelope
     *
     * @throws \Exception
     */
    public function handleAction(Request $request): Envelope
    {
        $request->attributes->set('pbjx_redact_error_message', false);
        $envelope = EnvelopeV1::create();
        if (!$this->isRequestMethodOk($envelope, $request) || !$this->isContentTypeOk($envelope, $request)) {
            return $envelope;
        }

        if ($request->isMethod('GET')) {
            $json = base64_decode($request->query->get('pbj'));
        } elseif (0 === strpos($request->headers->get('Content-Type'), 'multipart/form-data')) {
            $json = $request->request->get('pbj');
        } else {
            $json = $request->getContent();
        }

        $data = json_decode((string)$json, true) ?: [];
        if (!$this->isJsonOk($envelope, $request)) {
            return $envelope;
        }

        /*
        if (empty($data)) {
            return $envelope
                ->set('code', Code::INVALID_ARGUMENT)
                ->set('http_code', HttpCode::HTTP_UNSUPPORTED_MEDIA_TYPE())
                ->set('error_name', 'UnsupportedMediaType')
                ->set('error_message', 'Empty payload is not supported.');
        }
        */

        $pbjxCategory = $request->attributes->get('pbjx_category');
        if (false !== strpos($pbjxCategory, '_')) {
            $pbjxCategory = str_replace('_', '', $pbjxCategory);
            $request->attributes->set('pbjx_category', $pbjxCategory);
        }

        try {
            $expectedCurie = SchemaCurie::fromString(sprintf(
                '%s:%s:%s:%s',
                $request->attributes->get('pbjx_vendor'),
                $request->attributes->get('pbjx_package'),
                $pbjxCategory,
                $request->attributes->get('pbjx_message')
            ));

            $request->attributes->set('pbjx_curie', $expectedCurie->toString());
            $schemaId = isset($data[Schema::PBJ_FIELD_NAME]) ? SchemaId::fromString($data[Schema::PBJ_FIELD_NAME]) : null;

            /** @var Message $class */
            if (null === $schemaId) {
                $class = MessageResolver::resolveCurie($expectedCurie);
                $schema = $class::schema();
                $schemaId = $schema->getId();
            } else {
                $class = MessageResolver::resolveId($schemaId);
            }

            if ($schemaId->getCurie() !== $expectedCurie) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'The resolved schema [%s] doesn\'t match expected curie [%s].',
                        $schemaId->toString(),
                        $expectedCurie->toString()
                    )
                );
            }
        } catch (\Exception $e) {
            return $envelope
                ->set('code', Code::INVALID_ARGUMENT)
                ->set('http_code', HttpCode::HTTP_UNPROCESSABLE_ENTITY())
                ->set('error_name', 'UnprocessableEntity')
                ->set('error_message', $e->getMessage());
        }

        $request->attributes->set('pbjx_input', $data);
        $request->attributes->set('pbjx_bind_unrestricted', $request->attributes->getBoolean('pbjx_bind_unrestricted'));

        try {
            /** @var Command|Event|PbjxRequest $message */
            $message = $class::fromArray($data);
            $message->set('ctx_correlator_ref', $envelope->generateMessageRef());
        } catch (\Exception $e) {
            return $envelope
                ->set('code', Code::INVALID_ARGUMENT)
                ->set('http_code', HttpCode::HTTP_UNPROCESSABLE_ENTITY())
                ->set('error_name', 'UnprocessableEntity')
                ->set('error_message', $e->getMessage());
        }

        $request->attributes->set(
            'pbjx_redact_error_message',
            !$request->attributes->getBoolean('pbjx_console')
        );

        if ($message instanceof Command) {
            return $this->handleCommand($envelope, $request, $message);
        }

        if ($message instanceof Event) {
            return $this->handleEvent($envelope, $request, $message);
        }

        if ($message instanceof PbjxRequest) {
            return $this->handleRequest($envelope, $request, $message);
        }

        $request->attributes->set('pbjx_redact_error_message', false);
        return $envelope
            ->set('code', Code::INVALID_ARGUMENT)
            ->set('http_code', HttpCode::HTTP_UNPROCESSABLE_ENTITY())
            ->set('error_name', 'UnprocessableEntity')
            ->set(
                'error_message',
                sprintf('This service does not allow you to submit [%s] messages.', $schemaId->toString())
            );
    }

    /**
     * @param Envelope $envelope
     * @param Request  $request
     * @param Command  $command
     *
     * @return Envelope
     */
    private function handleCommand(Envelope $envelope, Request $request, Command $command): Envelope
    {
        try {
            $this->pbjx->send($command);
        } catch (\Exception $e) {
            return $this->handleException($envelope, $request, $command, $e);
        }

        $envelope
            ->set('code', Code::OK)
            ->set('http_code', HttpCode::HTTP_ACCEPTED())
            ->set('message_ref', $command->generateMessageRef());

        if ($request->attributes->getBoolean('pbjx_console')) {
            $envelope->set('message', $command);
        }

        return $envelope;
    }

    /**
     * @param Envelope $envelope
     * @param Request  $request
     * @param Event    $event
     *
     * @return Envelope
     */
    private function handleEvent(Envelope $envelope, Request $request, Event $event): Envelope
    {
        try {
            $this->pbjx->publish($event);
        } catch (\Exception $e) {
            return $this->handleException($envelope, $request, $event, $e);
        }

        $envelope
            ->set('code', Code::OK)
            ->set('http_code', HttpCode::HTTP_ACCEPTED())
            ->set('message_ref', $event->generateMessageRef());

        if ($request->attributes->getBoolean('pbjx_console')) {
            $envelope->set('message', $event);
        }

        return $envelope;
    }

    /**
     * @param Envelope    $envelope
     * @param Request     $request
     * @param PbjxRequest $pbjxRequest
     *
     * @return Envelope
     */
    private function handleRequest(Envelope $envelope, Request $request, PbjxRequest $pbjxRequest): Envelope
    {
        try {
            $response = $this->pbjx->request($pbjxRequest);
        } catch (\Exception $e) {
            return $this->handleException($envelope, $request, $pbjxRequest, $e);
        }

        return $envelope
            ->set('code', Code::OK)
            ->set('http_code', HttpCode::HTTP_OK())
            ->set('etag', $response->get('etag'))
            ->set('message_ref', $response->generateMessageRef())
            ->set('message', $response);
    }

    /**
     * @param Envelope   $envelope
     * @param Request    $request
     * @param Message    $message
     * @param \Exception $exception
     *
     * @return Envelope
     */
    private function handleException(Envelope $envelope, Request $request, Message $message, \Exception $exception): Envelope
    {
        if ($exception instanceof HasEndUserMessage) {
            $code = $exception->getCode();
            $httpCode = StatusCodeConverter::vendorToHttp($code);
            $errorName = ClassUtils::getShortName($exception);
            $errorMessage = $exception->getEndUserMessage();
            $request->attributes->set('pbjx_redact_error_message', false);
        } elseif ($exception instanceof HttpExceptionInterface) {
            $code = StatusCodeConverter::httpToVendor($exception->getStatusCode());
            $httpCode = $exception->getStatusCode();
            $errorName = ClassUtils::getShortName($exception);
            $errorMessage = $exception->getMessage();
        } elseif ($exception instanceof RequestHandlingFailed) {
            $response = $exception->getResponse();
            $code = $response->get('error_code', Code::UNKNOWN);
            $httpCode = StatusCodeConverter::vendorToHttp($code);
            $errorName = $response->get('error_name', ClassUtils::getShortName($exception));
            $errorMessage = $response->get('error_message', $exception->getMessage());
        } elseif ($exception instanceof GdbotsPbjException) {
            $code = Code::INVALID_ARGUMENT;
            $httpCode = HttpCode::HTTP_UNPROCESSABLE_ENTITY;
            $errorName = ClassUtils::getShortName($exception);
            $errorMessage = $exception->getMessage();
            // these error messages are safe to show as they only indicate schema problems
            $request->attributes->set('pbjx_redact_error_message', false);
        } else {
            $code = $exception->getCode() > 0 ? $exception->getCode() : Code::INVALID_ARGUMENT;
            $httpCode = StatusCodeConverter::vendorToHttp($code);
            $errorName = ClassUtils::getShortName($exception);
            $errorMessage = $exception->getMessage();
        }

        return $envelope
            ->set('code', $code)
            ->set('http_code', HttpCode::create($httpCode))
            ->set('error_name', $errorName)
            ->set('error_message', $errorMessage);
    }

    /**
     * @param Envelope $envelope
     * @param Request  $request
     *
     * @return bool
     */
    private function isRequestMethodOk(Envelope $envelope, Request $request): bool
    {
        if (in_array($request->getMethod(), $this->allowedMethods)) {
            return true;
        }

        $envelope
            ->set('code', Code::UNIMPLEMENTED)
            ->set('http_code', HttpCode::HTTP_METHOD_NOT_ALLOWED())
            ->set('error_name', 'MethodNotAllowed')
            ->set(
                'error_message',
                sprintf('You can only use HTTP [%s] on this service.', implode(',', $this->allowedMethods))
            );

        return false;
    }

    /**
     * @param Envelope $envelope
     * @param Request  $request
     *
     * @return bool
     */
    private function isContentTypeOk(Envelope $envelope, Request $request): bool
    {
        if ($request->query->has('callback') && $request->isMethod('GET')) {
            // jsonp request, don't enforce
            $request->attributes->set('_jsonp_enabled', true);
            return true;
        }

        $request->attributes->set('_jsonp_enabled', false);
        $contentType = $request->headers->get('Content-Type');

        if (0 === strpos($contentType, 'multipart/form-data') || 0 === strpos($contentType, 'application/json')) {
            return true;
        }

        $envelope
            ->set('code', Code::INVALID_ARGUMENT)
            ->set('http_code', HttpCode::HTTP_NOT_ACCEPTABLE())
            ->set('error_name', 'NotAcceptable')
            ->set(
                'error_message',
                sprintf(
                    'This service supports [application/json] or [multipart/form-data], you provided [%s].',
                    $contentType
                )
            );

        return false;
    }

    /**
     * @param Envelope $envelope
     * @param Request  $request
     *
     * @return bool
     */
    private function isJsonOk(Envelope $envelope, Request $request): bool
    {
        if (JSON_ERROR_NONE === json_last_error()) {
            return true;
        }

        $envelope
            ->set('code', Code::INVALID_ARGUMENT)
            ->set('http_code', HttpCode::HTTP_UNSUPPORTED_MEDIA_TYPE())
            ->set('error_name', 'UnsupportedMediaType')
            ->set('error_message', 'Invalid json: ' . $this->getLastJsonErrorMessage());

        return false;
    }

    /**
     * Resolves json_last_error message.
     *
     * @return string
     */
    private function getLastJsonErrorMessage(): string
    {
        if (function_exists('json_last_error_msg')) {
            return json_last_error_msg();
        }

        switch (json_last_error()) {
            case JSON_ERROR_DEPTH:
                return 'Maximum stack depth exceeded';
            case JSON_ERROR_STATE_MISMATCH:
                return 'Underflow or the modes mismatch';
            case JSON_ERROR_CTRL_CHAR:
                return 'Unexpected control character found';
            case JSON_ERROR_SYNTAX:
                return 'Syntax error, malformed JSON';
            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded';
            default:
                return 'Unknown error';
        }
    }
}
