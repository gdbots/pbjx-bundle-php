<?php

namespace Gdbots\Bundle\PbjxBundle\Util;

use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Enum\HttpStatusCode;

/**
 * Simple conversions from our "Code" aka vendor codes
 * to http status codes and back.
 */
final class StatusCodeConverter
{
    /**
     * @param int $code
     * @return int
     */
    public static function vendorToHttp($code = Code::OK)
    {
        if (Code::OK === $code) {
            return HttpStatusCode::HTTP_OK;
        }

        switch ($code) {
            case Code::CANCELLED:
                return HttpStatusCode::HTTP_CLIENT_CLOSED_REQUEST;

            case Code::UNKNOWN:
                return HttpStatusCode::HTTP_INTERNAL_SERVER_ERROR;

            case Code::INVALID_ARGUMENT:
                return HttpStatusCode::HTTP_BAD_REQUEST;

            case Code::DEADLINE_EXCEEDED:
                return HttpStatusCode::HTTP_GATEWAY_TIMEOUT;

            case Code::NOT_FOUND:
                return HttpStatusCode::HTTP_NOT_FOUND;

            case Code::ALREADY_EXISTS:
                return HttpStatusCode::HTTP_CONFLICT;

            case Code::PERMISSION_DENIED:
                return HttpStatusCode::HTTP_FORBIDDEN;

            case Code::UNAUTHENTICATED:
                return HttpStatusCode::HTTP_UNAUTHORIZED;

            case Code::RESOURCE_EXHAUSTED:
                return HttpStatusCode::HTTP_TOO_MANY_REQUESTS;

            // questionable... may not always be etag related.
            case Code::FAILED_PRECONDITION:
                return HttpStatusCode::HTTP_PRECONDITION_FAILED;

            case Code::ABORTED:
                return HttpStatusCode::HTTP_CONFLICT;

            case Code::OUT_OF_RANGE:
                return HttpStatusCode::HTTP_BAD_REQUEST;

            case Code::UNIMPLEMENTED:
                return HttpStatusCode::HTTP_NOT_IMPLEMENTED;

            case Code::INTERNAL:
                return HttpStatusCode::HTTP_INTERNAL_SERVER_ERROR;

            case Code::UNAVAILABLE:
                return HttpStatusCode::HTTP_SERVICE_UNAVAILABLE;

            case Code::DATA_LOSS:
                return HttpStatusCode::HTTP_INTERNAL_SERVER_ERROR;

            default:
                return HttpStatusCode::HTTP_UNPROCESSABLE_ENTITY;
        }
    }

    /**
     * @param int $httpStatus
     * @return int
     */
    public static function httpToVendor($httpStatus = HttpStatusCode::HTTP_OK)
    {
        if ($httpStatus < 400) {
            return Code::OK;
        }

        switch ($httpStatus) {
            case HttpStatusCode::HTTP_CLIENT_CLOSED_REQUEST:
                return Code::CANCELLED;

            case HttpStatusCode::HTTP_INTERNAL_SERVER_ERROR:
                return Code::INTERNAL;

            case HttpStatusCode::HTTP_GATEWAY_TIMEOUT:
                return Code::DEADLINE_EXCEEDED;

            case HttpStatusCode::HTTP_NOT_FOUND:
                return Code::NOT_FOUND;

            case HttpStatusCode::HTTP_CONFLICT:
                return Code::ALREADY_EXISTS;

            case HttpStatusCode::HTTP_FORBIDDEN:
                return Code::PERMISSION_DENIED;

            case HttpStatusCode::HTTP_UNAUTHORIZED:
                return Code::UNAUTHENTICATED;

            case HttpStatusCode::HTTP_TOO_MANY_REQUESTS:
                return Code::RESOURCE_EXHAUSTED;

            case HttpStatusCode::HTTP_PRECONDITION_FAILED:
                return Code::FAILED_PRECONDITION;

            case HttpStatusCode::HTTP_NOT_IMPLEMENTED:
                return Code::UNIMPLEMENTED;

            case HttpStatusCode::HTTP_SERVICE_UNAVAILABLE:
                return Code::UNAVAILABLE;

            default:
                if ($httpStatus >= 500) {
                    return Code::INTERNAL;
                }

                if ($httpStatus >= 400) {
                    return Code::INVALID_ARGUMENT;
                }

                return Code::OK;
        }
    }
}
