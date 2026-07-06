<?php

declare(strict_types=1);

namespace Directorium\Api\Auth;

use InvalidArgumentException;

/**
 * Issues, rotates, and revokes API keys (#23). A secret is minted once, hashed, and
 * only its hash is stored ({@see IssuedKey} carries the plaintext back exactly once).
 * Rotation deactivates the old key and issues a fresh one for the same tenant;
 * revocation deactivates without deletion, so the audit trail survives.
 */
final class KeyIssuer
{
    private const SECRET_PREFIX = 'intro_';

    public function __construct(private readonly KeyStore $store)
    {
    }

    public function issue(string $tenantId, string $label = '', ?int $ratePerMinute = null): IssuedKey
    {
        $secret = self::SECRET_PREFIX . bin2hex(random_bytes(24));
        $key = new ApiKey(
            'k_' . bin2hex(random_bytes(6)),
            $tenantId,
            hash('sha256', $secret),
            true,
            $ratePerMinute,
            $label,
        );
        $this->store->putKey($key);

        return new IssuedKey($key, $secret);
    }

    public function rotate(string $keyId): IssuedKey
    {
        $existing = $this->store->findKey($keyId)
            ?? throw new InvalidArgumentException(sprintf('No API key "%s" to rotate.', $keyId));

        $this->deactivate($existing);

        return $this->issue($existing->tenantId, $existing->label, $existing->ratePerMinute);
    }

    public function revoke(string $keyId): void
    {
        $existing = $this->store->findKey($keyId);
        if ($existing !== null) {
            $this->deactivate($existing);
        }
    }

    private function deactivate(ApiKey $key): void
    {
        $this->store->putKey(new ApiKey(
            $key->id,
            $key->tenantId,
            $key->hash,
            false,
            $key->ratePerMinute,
            $key->label,
        ));
    }
}
