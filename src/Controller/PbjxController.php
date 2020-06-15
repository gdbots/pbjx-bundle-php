<?php
declare(strict_types=1);

namespace Gdbots\Bundle\PbjxBundle\Controller;

use Gdbots\Bundle\PbjxBundle\PbjxTokenSigner;
use Gdbots\Pbj\Exception\GdbotsPbjException;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbj\SchemaId;
use Gdbots\Pbj\Util\ClassUtil;
use Gdbots\Pbj\Util\StringUtil;
use Gdbots\Pbjx\Exception\RequestHandlingFailed;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\Util\StatusCodeUtil;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Enum\HttpCode;
use Gdbots\Schemas\Pbjx\EnvelopeV1;
use Gdbots\Schemas\Pbjx\Mixin\Command\CommandV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Request\RequestV1Mixin;
use Gdbots\Schemas\Pbjx\Request\RequestFailedResponseV1;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class PbjxController
{
    private Pbjx $pbjx;
    private PbjxTokenSigner $signer;
    private array $allowedMethods = ['POST'];

    /**
     * An array of curies (or regexes) that will
     * NOT require PbjxToken validation.
     *
     * @var string[]
     */
    private array $bypassTokenValidation = [];

    public function __construct(
        Pbjx $pbjx,
        PbjxTokenSigner $signer,
        bool $allowGetRequest = false,
        array $bypassTokenValidation = []
    ) {
        $this->pbjx = $pbjx;
        $this->signer = $signer;
        $this->bypassTokenValidation = $bypassTokenValidation;

        if ($allowGetRequest) {
            $this->allowedMethods = ['GET', 'POST'];
        }
    }

    public function handleAction(Request $request): Message
    {
        $request->attributes->set('pbjx_redact_error_message', false);
        $envelope = EnvelopeV1::create();
        if (!$this->isRequestMethodOk($envelope, $request) || !$this->isContentTypeOk($envelope, $request)) {
            return $envelope;
        }

        if ($request->isMethod('GET')) {
            $json = StringUtil::urlsafeB64Decode($request->query->get('pbj'));
        } elseif (0 === strpos($request->headers->get('Content-Type'), 'multipart/form-data')) {
            $json = $request->request->get('pbj');
        } else {
            $json = $request->getContent();
        }

        $json = (string)$json;
        $data = json_decode($json, true) ?: [];
        if (!$this->isJsonOk($envelope, $request)) {
            return $envelope;
        }

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

            if (null === $schemaId) {
                $class = MessageResolver::resolveCurie($expectedCurie);
                $schema = $class::schema();
                $schemaId = $schema->getId();
            } else {
                $class = MessageResolver::resolveId($schemaId);
                $schema = $class::schema();
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
        } catch (\Throwable $e) {
            return $envelope
                ->set(EnvelopeV1::CODE_FIELD, Code::INVALID_ARGUMENT)
                ->set(EnvelopeV1::HTTP_CODE_FIELD, HttpCode::HTTP_UNPROCESSABLE_ENTITY())
                ->set(EnvelopeV1::ERROR_NAME_FIELD, 'UnprocessableEntity')
                ->set(EnvelopeV1::ERROR_MESSAGE_FIELD, $e->getMessage());
        }

        $request->attributes->set('pbjx_input', $data);
        $request->attributes->set('pbjx_bind_unrestricted', $request->attributes->getBoolean('pbjx_bind_unrestricted'));

        try {
            $message = $class::fromArray($data);
            $message->set(CommandV1Mixin::CTX_CORRELATOR_REF_FIELD, $envelope->generateMessageRef());
        } catch (\Throwable $e) {
            return $envelope
                ->set(EnvelopeV1::CODE_FIELD, Code::INVALID_ARGUMENT)
                ->set(EnvelopeV1::HTTP_CODE_FIELD, HttpCode::HTTP_UNPROCESSABLE_ENTITY())
                ->set(EnvelopeV1::ERROR_NAME_FIELD, 'UnprocessableEntity')
                ->set(EnvelopeV1::ERROR_MESSAGE_FIELD, $e->getMessage());
        }

        $request->attributes->set(
            'pbjx_redact_error_message',
            !$request->attributes->getBoolean('pbjx_console')
        );

        if (!$this->isPbjxTokenOk($envelope, $request, $json)) {
            return $envelope;
        }

        // allows for functional tests/postman tests/etc. to post pbjx but
        // not actually run them.  this is most important for commands/events
        // which can change state but for request, you'd typically not use
        // dry run because you need to get a response in order to make assertions.
        if ($request->headers->has('x-pbjx-dry-run')) {
            return $envelope
                ->set(EnvelopeV1::CODE_FIELD, Code::OK)
                ->set(EnvelopeV1::HTTP_CODE_FIELD, HttpCode::HTTP_ACCEPTED())
                ->set(EnvelopeV1::MESSAGE_REF_FIELD, $message->generateMessageRef());
        }

        if ($schema->hasMixin(CommandV1Mixin::SCHEMA_CURIE)) {
            return $this->handleCommand($envelope, $request, $message);
        }

        if ($schema->hasMixin(EventV1Mixin::SCHEMA_CURIE)) {
            return $this->handleEvent($envelope, $request, $message);
        }

        if ($schema->hasMixin(RequestV1Mixin::SCHEMA_CURIE)) {
            return $this->handleRequest($envelope, $request, $message);
        }

        $request->attributes->set('pbjx_redact_error_message', false);
        return $envelope
            ->set(EnvelopeV1::CODE_FIELD, Code::INVALID_ARGUMENT)
            ->set(EnvelopeV1::HTTP_CODE_FIELD, HttpCode::HTTP_UNPROCESSABLE_ENTITY())
            ->set(EnvelopeV1::ERROR_NAME_FIELD, 'UnprocessableEntity')
            ->set(
                EnvelopeV1::ERROR_MESSAGE_FIELD,
                sprintf('This service does not allow you to submit [%s] messages.', $schemaId->toString())
            );
    }

    private function handleCommand(Message $envelope, Request $request, Message $command): Message
    {
        try {
            $this->pbjx->send($command);
        } catch (\Throwable $e) {
            return $this->handleException($envelope, $request, $command, $e);
        }

        $envelope
            ->set(EnvelopeV1::CODE_FIELD, Code::OK)
            ->set(EnvelopeV1::HTTP_CODE_FIELD, HttpCode::HTTP_ACCEPTED())
            ->set(EnvelopeV1::MESSAGE_REF_FIELD, $command->generateMessageRef());

        if ($request->attributes->getBoolean('pbjx_console')) {
            $envelope->set(EnvelopeV1::MESSAGE_FIELD, $command);
        }

        return $envelope;
    }

    private function handleEvent(Message $envelope, Request $request, Message $event): Message
    {
        try {
            $this->pbjx->publish($event);
        } catch (\Throwable $e) {
            return $this->handleException($envelope, $request, $event, $e);
        }

        $envelope
            ->set(EnvelopeV1::CODE_FIELD, Code::OK)
            ->set(EnvelopeV1::HTTP_CODE_FIELD, HttpCode::HTTP_ACCEPTED())
            ->set(EnvelopeV1::MESSAGE_REF_FIELD, $event->generateMessageRef());

        if ($request->attributes->getBoolean('pbjx_console')) {
            $envelope->set(EnvelopeV1::MESSAGE_FIELD, $event);
        }

        return $envelope;
    }

    private function handleRequest(Message $envelope, Request $request, Message $pbjxRequest): Message
    {
        try {
            $response = $this->pbjx->request($pbjxRequest);
        } catch (\Throwable $e) {
            return $this->handleException($envelope, $request, $pbjxRequest, $e);
        }

        return $envelope
            ->set(EnvelopeV1::CODE_FIELD, Code::OK)
            ->set(EnvelopeV1::HTTP_CODE_FIELD, HttpCode::HTTP_OK())
            //->set(EnvelopeV1::ETAG_FIELD, $response->get(EnvelopeV1::ETAG_FIELD))
            ->set(EnvelopeV1::MESSAGE_REF_FIELD, $response->generateMessageRef())
            ->set(EnvelopeV1::MESSAGE_FIELD, $response);
    }

    private function handleException(Message $envelope, Request $request, Message $message, \Throwable $exception): Message
    {
        if ($exception instanceof HttpExceptionInterface) {
            $code = StatusCodeUtil::httpToVendor($exception->getStatusCode());
            $httpCode = $exception->getStatusCode();
            $errorName = ClassUtil::getShortName($exception);
            $errorMessage = $exception->getMessage();
        } elseif ($exception instanceof RequestHandlingFailed) {
            $response = $exception->getResponse();
            $code = $response->get(RequestFailedResponseV1::ERROR_CODE_FIELD, Code::UNKNOWN);
            $httpCode = StatusCodeUtil::vendorToHttp($code);
            $errorName = $response->get(RequestFailedResponseV1::ERROR_NAME_FIELD, ClassUtil::getShortName($exception));
            $errorMessage = $response->get(RequestFailedResponseV1::ERROR_MESSAGE_FIELD, $exception->getMessage());
        } elseif ($exception instanceof GdbotsPbjException) {
            $code = Code::INVALID_ARGUMENT;
            $httpCode = HttpCode::HTTP_UNPROCESSABLE_ENTITY;
            $errorName = ClassUtil::getShortName($exception);
            $errorMessage = $exception->getMessage();
            // these error messages are safe to show as they only indicate schema problems
            $request->attributes->set('pbjx_redact_error_message', false);
        } else {
            $code = $exception->getCode() > 0 ? $exception->getCode() : Code::INVALID_ARGUMENT;
            $httpCode = StatusCodeUtil::vendorToHttp($code);
            $errorName = ClassUtil::getShortName($exception);
            $errorMessage = $exception->getMessage();
        }

        return $envelope
            ->set(EnvelopeV1::CODE_FIELD, $code)
            ->set(EnvelopeV1::HTTP_CODE_FIELD, HttpCode::create($httpCode))
            ->set(EnvelopeV1::ERROR_NAME_FIELD, $errorName)
            ->set(EnvelopeV1::ERROR_MESSAGE_FIELD, $errorMessage);
    }

    private function isPbjxTokenOk(Message $envelope, Request $request, string $content): bool
    {
        if ($request->attributes->getBoolean('pbjx_console')) {
            // no tokens used on the console
            return true;
        }

        $curie = $request->attributes->get('pbjx_curie');
        foreach ($this->bypassTokenValidation as $pattern) {
            if ('all' === $pattern || $curie === $pattern) {
                return true;
            }

            if (preg_match('/' . trim($pattern, '/') . '/', $curie)) {
                return true;
            }
        }

        $token = $request->headers->get('x-pbjx-token');
        if (empty($token)) {
            $envelope
                ->set(EnvelopeV1::CODE_FIELD, Code::INVALID_ARGUMENT)
                ->set(EnvelopeV1::HTTP_CODE_FIELD, HttpCode::HTTP_BAD_REQUEST())
                ->set(EnvelopeV1::ERROR_NAME_FIELD, 'MissingPbjxToken')
                ->set(EnvelopeV1::ERROR_MESSAGE_FIELD, 'Missing x-pbjx-token header.');
            return false;
        }

        if ($request->headers->has('Authorization')) {
            $bearer = trim(str_ireplace('bearer ', '', $request->headers->get('Authorization')));
            $this->signer->addKey('bearer', $bearer);
        }

        if ($request->headers->has('x-pbjx-nonce')) {
            $this->signer->addKey('nonce', $request->headers->get('x-pbjx-nonce'));
        }

        try {
            $this->signer->validate($content, $request->getUri(), $token);
            return true;
        } catch (\Throwable $e) {
            $envelope
                ->set(EnvelopeV1::CODE_FIELD, Code::INVALID_ARGUMENT)
                ->set(EnvelopeV1::HTTP_CODE_FIELD, HttpCode::HTTP_BAD_REQUEST())
                ->set(EnvelopeV1::ERROR_NAME_FIELD, 'InvalidPbjxToken')
                ->set(EnvelopeV1::ERROR_MESSAGE_FIELD, $e->getMessage());
            return false;
        } finally {
            $this->signer->removeKey('bearer');
            $this->signer->removeKey('nonce');
        }
    }

    private function isRequestMethodOk(Message $envelope, Request $request): bool
    {
        if (in_array($request->getMethod(), $this->allowedMethods)) {
            return true;
        }

        $envelope
            ->set(EnvelopeV1::CODE_FIELD, Code::UNIMPLEMENTED)
            ->set(EnvelopeV1::HTTP_CODE_FIELD, HttpCode::HTTP_METHOD_NOT_ALLOWED())
            ->set(EnvelopeV1::ERROR_NAME_FIELD, 'MethodNotAllowed')
            ->set(
                EnvelopeV1::ERROR_MESSAGE_FIELD,
                sprintf('You can only use HTTP [%s] on this service.', implode(',', $this->allowedMethods))
            );

        return false;
    }

    private function isContentTypeOk(Message $envelope, Request $request): bool
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
            ->set(EnvelopeV1::CODE_FIELD, Code::INVALID_ARGUMENT)
            ->set(EnvelopeV1::HTTP_CODE_FIELD, HttpCode::HTTP_NOT_ACCEPTABLE())
            ->set(EnvelopeV1::ERROR_NAME_FIELD, 'NotAcceptable')
            ->set(
                EnvelopeV1::ERROR_MESSAGE_FIELD,
                sprintf(
                    'This service supports [application/json] or [multipart/form-data], you provided [%s].',
                    $contentType
                )
            );

        return false;
    }

    private function isJsonOk(Message $envelope, Request $request): bool
    {
        if (JSON_ERROR_NONE === json_last_error()) {
            return true;
        }

        $envelope
            ->set(EnvelopeV1::CODE_FIELD, Code::INVALID_ARGUMENT)
            ->set(EnvelopeV1::HTTP_CODE_FIELD, HttpCode::HTTP_UNSUPPORTED_MEDIA_TYPE())
            ->set(EnvelopeV1::ERROR_NAME_FIELD, 'UnsupportedMediaType')
            ->set(EnvelopeV1::ERROR_MESSAGE_FIELD, 'Invalid json: ' . json_last_error_msg());

        return false;
    }
}
