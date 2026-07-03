<?php

declare(strict_types=1);

namespace Introibo\Api\Contract;

/**
 * The stable, machine-readable error codes the service emits. The string value is
 * part of the public contract — a client may switch on it — so a code is never
 * renamed or repurposed; new codes are only ever added. Each carries its HTTP
 * status and a default human message.
 */
enum ErrorCode: string
{
    case MALFORMED_DATE = 'malformed_date';
    case DATE_OUT_OF_RANGE = 'date_out_of_range';
    case UNSUPPORTED_SYSTEM = 'unsupported_system';
    case UNKNOWN_CALENDAR = 'unknown_calendar';
    case UNSUPPORTED_LANGUAGE = 'unsupported_language';
    case NOT_FOUND = 'not_found';
    case METHOD_NOT_ALLOWED = 'method_not_allowed';
    case UNAUTHENTICATED = 'unauthenticated';
    case RATE_LIMITED = 'rate_limited';
    case QUOTA_EXCEEDED = 'quota_exceeded';
    case INTERNAL = 'internal_error';

    public function status(): int
    {
        return match ($this) {
            self::MALFORMED_DATE => 400,
            self::UNAUTHENTICATED => 401,
            self::DATE_OUT_OF_RANGE,
            self::UNSUPPORTED_SYSTEM,
            self::UNKNOWN_CALENDAR,
            self::UNSUPPORTED_LANGUAGE => 422,
            self::NOT_FOUND => 404,
            self::METHOD_NOT_ALLOWED => 405,
            self::RATE_LIMITED,
            self::QUOTA_EXCEEDED => 429,
            self::INTERNAL => 500,
        };
    }

    public function defaultMessage(): string
    {
        return match ($this) {
            self::MALFORMED_DATE => 'The date must be an ISO calendar date in the form YYYY-MM-DD.',
            self::DATE_OUT_OF_RANGE => 'The date is outside the range this service resolves.',
            self::UNSUPPORTED_SYSTEM => 'The requested rubric system is not supported.',
            self::UNKNOWN_CALENDAR => 'The requested calendar is not known to this service.',
            self::UNSUPPORTED_LANGUAGE => 'The requested language is not supported.',
            self::NOT_FOUND => 'No resource matches this path.',
            self::METHOD_NOT_ALLOWED => 'The HTTP method is not allowed for this path.',
            self::UNAUTHENTICATED => 'A valid API key is required.',
            self::RATE_LIMITED => 'Too many requests — slow down.',
            self::QUOTA_EXCEEDED => 'The account request quota has been exhausted.',
            self::INTERNAL => 'The service encountered an unexpected error.',
        };
    }
}
