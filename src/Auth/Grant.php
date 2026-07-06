<?php

declare(strict_types=1);

namespace Directorium\Api\Auth;

/**
 * The outcome of a successful authorisation: the tenant the request is scoped to
 * (null when access control is disabled) and the rate-limit headers to echo on the
 * response.
 */
final readonly class Grant
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public ?Tenant $tenant = null,
        public array $headers = [],
    ) {
    }

    public static function open(): self
    {
        return new self();
    }
}
