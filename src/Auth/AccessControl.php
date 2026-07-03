<?php

declare(strict_types=1);

namespace Introibo\Api\Auth;

use DateTimeImmutable;
use Introibo\Api\Contract\ApiException;
use Introibo\Api\Contract\ErrorCode;
use Introibo\Api\Http\Request;
use PDO;

/**
 * The access gate for the metered endpoints (#24/#25/#26): authenticate the request
 * to a key, scope it to that key's tenant, enforce the key's per-minute rate limit
 * and the tenant's monthly quota, and hand back the rate-limit headers to echo.
 *
 * When no {@see KeyStore} is configured the gate is **open** — {@see authorise()}
 * returns an empty grant and nothing is metered — so the service runs identically
 * without the auth database (the default in dev and tests). Production enables it by
 * pointing {@see fromEnvironment()} at MySQL.
 */
final class AccessControl
{
    private const DEFAULT_RATE_PER_MINUTE = 60;

    public function __construct(
        private readonly ?KeyStore $store = null,
        private readonly ?Clock $clock = null,
    ) {
    }

    /** An open gate: no authentication, quota, or rate limiting. */
    public static function open(): self
    {
        return new self();
    }

    /**
     * Build the gate from the environment: `INTROIBO_DB_DSN` (+ `_USER`/`_PASSWORD`)
     * enables MySQL-backed access control; unset leaves the gate open.
     */
    public static function fromEnvironment(): self
    {
        $dsn = getenv('INTROIBO_DB_DSN');
        if ($dsn === false || $dsn === '') {
            return self::open();
        }

        $user = getenv('INTROIBO_DB_USER');
        $password = getenv('INTROIBO_DB_PASSWORD');
        $pdo = new PDO(
            $dsn,
            $user === false ? null : $user,
            $password === false ? null : $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        return new self(new PdoKeyStore($pdo), new SystemClock());
    }

    public function enabled(): bool
    {
        return $this->store !== null;
    }

    /**
     * Authorise a request, or throw the canonical {@see ApiException} — 401 when the
     * key is missing/invalid, 429 (with rate headers) when a limit is hit.
     */
    public function authorise(Request $request): Grant
    {
        $store = $this->store;
        if ($store === null) {
            return Grant::open();
        }

        $now = ($this->clock ?? new SystemClock())->now();

        $key = $this->authenticate($store, $request);
        $tenant = $store->findTenant($key->tenantId)
            ?? throw ApiException::of(ErrorCode::UNAUTHENTICATED, 'The API key is not associated with an account.');

        $rateHeaders = $this->enforceRate($store, $key, $now);
        $this->enforceQuota($store, $tenant, $now);

        return new Grant($tenant, $rateHeaders);
    }

    private function authenticate(KeyStore $store, Request $request): ApiKey
    {
        $secret = $this->presentedSecret($request);
        if ($secret === null) {
            throw ApiException::of(
                ErrorCode::UNAUTHENTICATED,
                'Provide an API key via the "Authorization: Bearer <key>" or "X-API-Key" header.',
            );
        }

        $key = $store->findKeyByHash(hash('sha256', $secret));
        if ($key === null || !$key->active) {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED, 'The API key is invalid or has been revoked.');
        }

        return $key;
    }

    private function presentedSecret(Request $request): ?string
    {
        $authorization = $request->header('authorization');
        if ($authorization !== null && stripos($authorization, 'bearer ') === 0) {
            return trim(substr($authorization, 7));
        }

        return $request->header('x-api-key');
    }

    /**
     * @return array<string, string>
     */
    private function enforceRate(KeyStore $store, ApiKey $key, DateTimeImmutable $now): array
    {
        $limit = $key->ratePerMinute ?? self::DEFAULT_RATE_PER_MINUTE;
        $count = $store->bumpRate($key->id, $now->format('Y-m-d H:i'));

        $epoch = (int) $now->format('U');
        $reset = $epoch - (int) $now->format('s') + 60;
        $headers = [
            'x-ratelimit-limit' => (string) $limit,
            'x-ratelimit-remaining' => (string) max(0, $limit - $count),
            'x-ratelimit-reset' => (string) $reset,
        ];

        if ($count > $limit) {
            throw ApiException::of(ErrorCode::RATE_LIMITED)
                ->withHeaders($headers + ['retry-after' => (string) max(1, $reset - $epoch)]);
        }

        return $headers;
    }

    private function enforceQuota(KeyStore $store, Tenant $tenant, DateTimeImmutable $now): void
    {
        if ($tenant->monthlyQuota === null) {
            return;
        }

        $used = $store->bumpUsage($tenant->id, $now->format('Y-m'));
        if ($used > $tenant->monthlyQuota) {
            throw ApiException::of(
                ErrorCode::QUOTA_EXCEEDED,
                sprintf('The monthly quota of %d requests is exhausted.', $tenant->monthlyQuota),
            );
        }
    }
}
