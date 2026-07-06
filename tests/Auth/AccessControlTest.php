<?php

declare(strict_types=1);

namespace Directorium\Api\Tests\Auth;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Api\Auth\AccessControl;
use Directorium\Api\Auth\InMemoryKeyStore;
use Directorium\Api\Auth\KeyIssuer;
use Directorium\Api\Auth\Tenant;
use Directorium\Api\Contract\ApiException;
use Directorium\Api\Http\Request;
use Directorium\Api\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class AccessControlTest extends TestCase
{
    public function testOpenGateAllowsEverythingUnmetered(): void
    {
        $grant = AccessControl::open()->authorise(new Request('GET', '/v1/day/x'));

        self::assertNull($grant->tenant);
        self::assertSame([], $grant->headers);
    }

    public function testMissingKeyIsRejected(): void
    {
        $this->assertRejected(
            $this->access(new InMemoryKeyStore()),
            new Request('GET', '/v1/day/x'),
            'unauthenticated',
            401,
        );
    }

    public function testInvalidKeyIsRejected(): void
    {
        $store = $this->storeWithTenant();

        $this->assertRejected($this->access($store), $this->withKey('intro_wrong'), 'unauthenticated', 401);
    }

    public function testRevokedKeyIsRejected(): void
    {
        $store = $this->storeWithTenant();
        $issuer = new KeyIssuer($store);
        $issued = $issuer->issue('t');
        $issuer->revoke($issued->key->id);

        $this->assertRejected($this->access($store), $this->withKey($issued->secret), 'unauthenticated', 401);
    }

    public function testValidKeyGrantsTheTenantWithRateHeaders(): void
    {
        $store = $this->storeWithTenant(1000);
        $issued = (new KeyIssuer($store))->issue('t', 'main', 60);

        $grant = $this->access($store)->authorise($this->withKey($issued->secret));

        self::assertNotNull($grant->tenant);
        self::assertSame('t', $grant->tenant->id);
        self::assertSame('60', $grant->headers['x-ratelimit-limit']);
        self::assertSame('59', $grant->headers['x-ratelimit-remaining']);
    }

    public function testTheXApiKeyHeaderAlsoAuthenticates(): void
    {
        $store = $this->storeWithTenant();
        $issued = (new KeyIssuer($store))->issue('t');

        $grant = $this->access($store)->authorise(new Request('GET', '/', [], ['x-api-key' => $issued->secret]));

        self::assertNotNull($grant->tenant);
    }

    public function testRateLimitExceededReturns429WithRetryAfter(): void
    {
        $store = $this->storeWithTenant();
        $issued = (new KeyIssuer($store))->issue('t', 'main', 2);
        $access = $this->access($store);
        $request = $this->withKey($issued->secret);

        $access->authorise($request);
        $access->authorise($request);

        try {
            $access->authorise($request);
            self::fail('Expected a rate_limited ApiException.');
        } catch (ApiException $e) {
            self::assertSame('rate_limited', $e->error()->code);
            self::assertSame(429, $e->error()->status);
            self::assertSame('0', $e->headers()['x-ratelimit-remaining']);
            self::assertArrayHasKey('retry-after', $e->headers());
        }
    }

    public function testQuotaExceededReturns429(): void
    {
        $store = $this->storeWithTenant(2);
        $issued = (new KeyIssuer($store))->issue('t', 'main', 1000);
        $access = $this->access($store);
        $request = $this->withKey($issued->secret);

        $access->authorise($request);
        $access->authorise($request);

        $this->assertRejected($access, $request, 'quota_exceeded', 429);
    }

    private function access(InMemoryKeyStore $store): AccessControl
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-07-03 01:27:30', new DateTimeZone('UTC')));

        return new AccessControl($store, $clock);
    }

    private function storeWithTenant(?int $quota = null): InMemoryKeyStore
    {
        $store = new InMemoryKeyStore();
        $store->putTenant(new Tenant('t', 'Acme', $quota));

        return $store;
    }

    private function withKey(string $secret): Request
    {
        return new Request('GET', '/v1/day/x', [], ['authorization' => 'Bearer ' . $secret]);
    }

    private function assertRejected(AccessControl $access, Request $request, string $code, int $status): void
    {
        try {
            $access->authorise($request);
            self::fail(sprintf('Expected a %s ApiException.', $code));
        } catch (ApiException $e) {
            self::assertSame($code, $e->error()->code);
            self::assertSame($status, $e->error()->status);
        }
    }
}
