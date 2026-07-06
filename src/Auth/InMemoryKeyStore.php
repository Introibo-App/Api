<?php

declare(strict_types=1);

namespace Directorium\Api\Auth;

/**
 * An in-memory {@see KeyStore} for tests and local development — the double that
 * proves the auth, quota, and rate-limit logic without a database. Its semantics are
 * the contract the MySQL implementation must match.
 */
final class InMemoryKeyStore implements KeyStore
{
    /** @var array<string, ApiKey> Keyed by key id. */
    private array $keys = [];

    /** @var array<string, Tenant> Keyed by tenant id. */
    private array $tenants = [];

    /** @var array<string, int> Keyed by "tenantId|period". */
    private array $usage = [];

    /** @var array<string, int> Keyed by "keyId|window". */
    private array $rate = [];

    public function findKeyByHash(string $hash): ?ApiKey
    {
        foreach ($this->keys as $key) {
            if (hash_equals($key->hash, $hash)) {
                return $key;
            }
        }

        return null;
    }

    public function findKey(string $keyId): ?ApiKey
    {
        return $this->keys[$keyId] ?? null;
    }

    public function findTenant(string $tenantId): ?Tenant
    {
        return $this->tenants[$tenantId] ?? null;
    }

    public function putTenant(Tenant $tenant): void
    {
        $this->tenants[$tenant->id] = $tenant;
    }

    public function putKey(ApiKey $key): void
    {
        $this->keys[$key->id] = $key;
    }

    public function bumpUsage(string $tenantId, string $period): int
    {
        $slot = $tenantId . '|' . $period;

        return $this->usage[$slot] = ($this->usage[$slot] ?? 0) + 1;
    }

    public function bumpRate(string $keyId, string $window): int
    {
        $slot = $keyId . '|' . $window;

        return $this->rate[$slot] = ($this->rate[$slot] ?? 0) + 1;
    }
}
