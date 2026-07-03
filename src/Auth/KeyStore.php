<?php

declare(strict_types=1);

namespace Introibo\Api\Auth;

/**
 * Persistence for tenants, API keys, and the aggregate counters that back quotas
 * and rate limits (#22). Everything the auth layer needs from storage is behind this
 * one seam, so the service depends on the interface — the MySQL implementation
 * ({@see PdoKeyStore}) is a deploy concern, and tests use {@see InMemoryKeyStore}.
 *
 * Counters are **aggregate**: `bumpUsage`/`bumpRate` atomically increment a single
 * row and return the new total. There is never a row per request.
 */
interface KeyStore
{
    public function findKeyByHash(string $hash): ?ApiKey;

    public function findKey(string $keyId): ?ApiKey;

    public function findTenant(string $tenantId): ?Tenant;

    public function putTenant(Tenant $tenant): void;

    public function putKey(ApiKey $key): void;

    /**
     * Increment the tenant's request counter for a period (e.g. `2026-07`) and return
     * the new total.
     */
    public function bumpUsage(string $tenantId, string $period): int;

    /**
     * Increment the key's request counter for a minute window (e.g. `2026-07-03 01:27`)
     * and return the new count in that window.
     */
    public function bumpRate(string $keyId, string $window): int;
}
