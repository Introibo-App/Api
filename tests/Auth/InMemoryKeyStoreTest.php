<?php

declare(strict_types=1);

namespace Directorium\Api\Tests\Auth;

use Directorium\Api\Auth\ApiKey;
use Directorium\Api\Auth\InMemoryKeyStore;
use Directorium\Api\Auth\Tenant;
use PHPUnit\Framework\TestCase;

final class InMemoryKeyStoreTest extends TestCase
{
    public function testStoresAndFindsTenantsAndKeys(): void
    {
        $store = new InMemoryKeyStore();
        $tenant = new Tenant('t1', 'Acme', 1000);
        $key = new ApiKey('k1', 't1', 'hash-abc', true, 60, 'main');
        $store->putTenant($tenant);
        $store->putKey($key);

        self::assertSame($tenant, $store->findTenant('t1'));
        self::assertSame($key, $store->findKey('k1'));
        self::assertSame($key, $store->findKeyByHash('hash-abc'));
        self::assertNull($store->findKeyByHash('missing'));
        self::assertNull($store->findTenant('t2'));
    }

    public function testUsageCounterAccumulatesPerPeriod(): void
    {
        $store = new InMemoryKeyStore();

        self::assertSame(1, $store->bumpUsage('t', '2026-07'));
        self::assertSame(2, $store->bumpUsage('t', '2026-07'));
        self::assertSame(1, $store->bumpUsage('t', '2026-08'));
    }

    public function testRateCounterAccumulatesPerWindow(): void
    {
        $store = new InMemoryKeyStore();

        self::assertSame(1, $store->bumpRate('k', '2026-07-03 01:27'));
        self::assertSame(2, $store->bumpRate('k', '2026-07-03 01:27'));
        self::assertSame(1, $store->bumpRate('k', '2026-07-03 01:28'));
    }
}
