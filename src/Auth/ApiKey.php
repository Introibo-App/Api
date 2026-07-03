<?php

declare(strict_types=1);

namespace Introibo\Api\Auth;

/**
 * An API key belonging to a tenant. Only the SHA-256 **hash** of the secret is ever
 * stored or held here — the plaintext exists once, at issue time
 * ({@see IssuedKey}). A key can be deactivated (revoked) without deletion, and
 * carries its own per-minute rate limit.
 */
final readonly class ApiKey
{
    /**
     * @param int|null $ratePerMinute Requests per minute for this key, or null for the service default.
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $hash,
        public bool $active = true,
        public ?int $ratePerMinute = null,
        public string $label = '',
    ) {
    }
}
