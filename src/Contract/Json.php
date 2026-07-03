<?php

declare(strict_types=1);

namespace Introibo\Api\Contract;

use JsonException;
use RuntimeException;

/**
 * The one place the service encodes JSON, with the same frozen flags Core uses for
 * its contract: Unicode and slashes stay legible, and a failure throws rather than
 * silently returning `false`. Encoding here can only fail on a programming error
 * (a non-encodable value), so it is surfaced as a RuntimeException, never an
 * {@see ApiException}.
 */
final class Json
{
    public const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /**
     * @param array<string, mixed> $payload
     */
    public static function encode(array $payload): string
    {
        try {
            return json_encode($payload, self::FLAGS);
        } catch (JsonException $e) {
            throw new RuntimeException('Failed to encode response as JSON: ' . $e->getMessage(), 0, $e);
        }
    }
}
