<?php

declare(strict_types=1);

namespace Introibo\Api\Auth;

/**
 * A freshly issued key: the stored {@see ApiKey} record plus the **plaintext secret**,
 * which is shown to the tenant exactly once and never persisted. The caller must hand
 * the secret over immediately; only its hash survives in the store.
 */
final readonly class IssuedKey
{
    public function __construct(
        public ApiKey $key,
        public string $secret,
    ) {
    }
}
