<?php

namespace Gdbots\Bundle\PbjxBundle\Controller;

use Gdbots\Bundle\PbjxBundle\Util\StatusCodeConverter;
use Gdbots\Common\Util\ClassUtils;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageCurie;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\SchemaId;
use Gdbots\Pbjx\Exception\RequestHandlingFailed;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Command\Command;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Enum\HttpStatusCode;
use Gdbots\Schemas\Pbjx\Envelope;
use Gdbots\Schemas\Pbjx\EnvelopeV1;
use Gdbots\Schemas\Pbjx\Event\Event;
use Gdbots\Schemas\Pbjx\Request\Request as PbjxRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PbjxController
{
    /** @var Pbjx */
    protected $pbjx;

    /** @var string[] */
    protected $allowedMethods = ['POST'];

    /**
     * @param Pbjx $pbjx
     * @param bool $allowGetRequest
     */
    public function __construct(Pbjx $pbjx, $allowGetRequest = false)
    {
        $this->pbjx = $pbjx;
        if (true === filter_var($allowGetRequest, FILTER_VALIDATE_BOOLEAN)) {
            $this->allowedMethods = ['GET', 'POST'];
        }
    }

    /**
     * @param Request $request
     * @return Envelope
     *
     * @throws \Exception
     */
    public function handleAction(Request $request)
    {
        $envelope = EnvelopeV1::create();
        if (!$this->isRequestMethodOk($envelope, $request) || !$this->isContentTypeOk($envelope, $request)) {
            return $envelope;
        }

        $json = $request->isMethod('GET') ? base64_decode($request->query->get('data')) : $request->getContent();
        $data = json_decode($json, true) ?: [];
        if (!$this->isJsonOk($envelope, $request)) {
            return $envelope;
        }

        if (empty($data)) {
            return $envelope
                ->set('code', Code::INVALID_ARGUMENT)
                ->set('http_status_code', HttpStatusCode::HTTP_UNSUPPORTED_MEDIA_TYPE())
                ->set('error_name', 'UnsupportedMediaType')
                ->set('error_message', 'Empty payload is not supported.');
        }

        $pbjxCategory = $request->attributes->get('pbjx_category');
        if (false !== strpos($pbjxCategory, '_')) {
            $pbjxCategory = str_replace('_', '', $pbjxCategory);
            $request->attributes->set('pbjx_category', $pbjxCategory);
        }

        try {
            $expectedCurie = MessageCurie::fromString(sprintf(
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
                $class = MessageResolver::resolveMessageCurie($expectedCurie);
                $schema = $class::schema();
                $schemaId = $schema->getId();
            } else {
                $class = MessageResolver::resolveSchemaId($schemaId);
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
                ->set('http_status_code', HttpStatusCode::HTTP_UNPROCESSABLE_ENTITY())
                ->set('error_name', 'UnprocessableEntity')
                ->set('error_message', $e->getMessage());
        }

        $request->attributes->set('pbjx_input', $data);
        $request->attributes->set('pbjx_bind_unrestricted', false);

        try {
            $message = $class::fromArray($data)->validate();
            $message->set('correlator', $envelope->generateMessageRef());
        } catch (\Exception $e) {
            return $envelope
                ->set('code', Code::INVALID_ARGUMENT)
                ->set('http_status_code', HttpStatusCode::HTTP_UNPROCESSABLE_ENTITY())
                ->set('error_name', 'UnprocessableEntity')
                ->set('error_message', $e->getMessage());
        }

        if ($message instanceof Command) {
            return $this->handleCommand($envelope, $request, $message);
        }

        if ($message instanceof Event) {
            return $this->handleEvent($envelope, $request, $message);
        }

        if ($message instanceof PbjxRequest) {
            return $this->handleRequest($envelope, $request, $message);
        }

        return $envelope
            ->set('code', Code::INVALID_ARGUMENT)
            ->set('http_status_code', HttpStatusCode::HTTP_UNPROCESSABLE_ENTITY())
            ->set('error_name', 'UnprocessableEntity')
            ->set(
                'error_message',
                sprintf('This service does not allow you to submit [%s] messages.', $schemaId->toString())
            );
    }

    /**
     * @param Envelope $envelope
     * @param Request $request
     * @param Command $command
     *
     * @return Envelope
     */
    protected function handleCommand(Envelope $envelope, Request $request, Command $command)
    {
        try {
            $this->pbjx->send($command);
        } catch (\Exception $e) {
            return $this->handleException($envelope, $request, $command, $e);
        }

        return $envelope
            ->set('code', Code::OK)
            ->set('http_status_code', HttpStatusCode::HTTP_ACCEPTED())
            ->set('message_ref', $command->generateMessageRef());
            //->set('message', $command);
    }

    /**
     * @param Envelope $envelope
     * @param Request $request
     * @param Event $event
     *
     * @return Envelope
     */
    protected function handleEvent(Envelope $envelope, Request $request, Event $event)
    {
        try {
            $this->pbjx->publish($event);
        } catch (\Exception $e) {
            return $this->handleException($envelope, $request, $event, $e);
        }

        return $envelope
            ->set('code', Code::OK)
            ->set('http_status_code', HttpStatusCode::HTTP_ACCEPTED())
            ->set('message_ref', $event->generateMessageRef());
    }

    /**
     * @param Envelope $envelope
     * @param Request $request
     * @param PbjxRequest $pbjxRequest
     *
     * @return Envelope
     */
    protected function handleRequest(Envelope $envelope, Request $request, PbjxRequest $pbjxRequest)
    {
        try {
            $response = $this->pbjx->request($pbjxRequest);
        } catch (\Exception $e) {
            return $this->handleException($envelope, $request, $pbjxRequest, $e);
        }

        return $envelope
            ->set('code', Code::OK)
            ->set('http_status_code', HttpStatusCode::HTTP_OK())
            ->set('etag', $response->get('etag'))
            ->set('message_ref', $response->generateMessageRef())
            ->set('message', $response);
    }

    /**
     * @param Envelope $envelope
     * @param Request $request
     * @param Message $message
     * @param \Exception $exception
     *
     * @return Envelope
     */
    protected function handleException(Envelope $envelope, Request $request, Message $message, \Exception $exception)
    {
        if ($exception instanceof HttpExceptionInterface) {
            $code = StatusCodeConverter::httpToVendor($exception->getStatusCode());
            $httpStatusCode = $exception->getStatusCode();
            $errorName = ClassUtils::getShortName($exception);
            $errorMessage = $exception->getMessage();

        } elseif ($exception instanceof RequestHandlingFailed) {
            $response = $exception->getResponse();
            $code = $response->get('error_code', Code::UNKNOWN);
            $httpStatusCode = StatusCodeConverter::vendorToHttp($code);
            $errorName = $response->get('error_name', ClassUtils::getShortName($exception));
            $errorMessage = $response->get('error_message', $exception->getMessage());

        } else {
            $code = $exception->getCode() > 0 ? $exception->getCode() : Code::INVALID_ARGUMENT;
            $httpStatusCode = StatusCodeConverter::vendorToHttp($code);
            $errorName = ClassUtils::getShortName($exception);
            $errorMessage = $exception->getMessage();
        }

        return $envelope
            ->set('code', $code)
            ->set('http_status_code', HttpStatusCode::create($httpStatusCode))
            ->set('error_name', $errorName)
            ->set('error_message', $errorMessage);
    }

    /**
     * @param Envelope $envelope
     * @param Request $request
     *
     * @return bool
     */
    protected function isRequestMethodOk(Envelope $envelope, Request $request)
    {
        if (in_array($request->getMethod(), $this->allowedMethods)) {
            return true;
        }

        $envelope
            ->set('code', Code::UNIMPLEMENTED)
            ->set('http_status_code', HttpStatusCode::HTTP_METHOD_NOT_ALLOWED())
            ->set('error_name', 'MethodNotAllowed')
            ->set(
                'error_message',
                sprintf('You can only use HTTP [%s] on this service.', implode(',', $this->allowedMethods))
            );

        return false;
    }

    /**
     * @param Envelope $envelope
     * @param Request $request
     *
     * @return bool
     */
    protected function isContentTypeOk(Envelope $envelope, Request $request)
    {
        if ($request->query->has('callback') && $request->isMethod('GET')) {
            // jsonp request, don't enforce
            $request->attributes->set('_jsonp_enabled', true);
            return true;
        }

        $request->attributes->set('_jsonp_enabled', false);
        if (0 === strpos($request->headers->get('CONTENT_TYPE'), 'application/json')) {
            return true;
        }

        $envelope
            ->set('code', Code::INVALID_ARGUMENT)
            ->set('http_status_code', HttpStatusCode::HTTP_NOT_ACCEPTABLE())
            ->set('error_name', 'NotAcceptable')
            ->set(
                'error_message',
                sprintf(
                    'This service supports [application/json], you provided [%s].',
                    $request->headers->get('CONTENT_TYPE')
                )
            );

        return false;
    }

    /**
     * @param Envelope $envelope
     * @param Request $request
     *
     * @return bool
     */
    protected function isJsonOk(Envelope $envelope, Request $request)
    {
        if (JSON_ERROR_NONE === json_last_error()) {
            return true;
        }

        $envelope
            ->set('code', Code::INVALID_ARGUMENT)
            ->set('http_status_code', HttpStatusCode::HTTP_UNSUPPORTED_MEDIA_TYPE())
            ->set('error_name', 'UnsupportedMediaType')
            ->set('error_message', 'Invalid json: ' . $this->getLastJsonErrorMessage());

        return false;
    }

    /**
     * Resolves json_last_error message.
     *
     * @return string
     */
    protected function getLastJsonErrorMessage()
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
