<?php

declare(strict_types=1);

namespace Introibo\Api\Contract;

use RuntimeException;
use Throwable;

/**
 * A recoverable, client-facing failure carrying the {@see ApiError} to return.
 * Handlers and the request parser throw this; the kernel catches it and renders
 * the error envelope at the right status. Anything that is *not* an ApiException
 * escaping to the kernel becomes an opaque 500, so internal detail never leaks.
 */
final class ApiException extends RuntimeException
{
    private function __construct(private readonly ApiError $error, ?Throwable $previous = null)
    {
        parent::__construct($error->message, $error->status, $previous);
    }

    public static function of(ErrorCode $code, ?string $message = null, ?Throwable $previous = null): self
    {
        return new self(ApiError::of($code, $message), $previous);
    }

    public function error(): ApiError
    {
        return $this->error;
    }
}
