<?php

declare(strict_types=1);

namespace Directorium\Api\Tests\Auth;

use Directorium\Api\Auth\InMemoryKeyStore;
use Directorium\Api\Auth\KeyIssuer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class KeyIssuerTest extends TestCase
{
    public function testIssueMintsAnActiveHashedKey(): void
    {
        $store = new InMemoryKeyStore();
        $issued = (new KeyIssuer($store))->issue('t1', 'main', 120);

        self::assertStringStartsWith('intro_', $issued->secret);
        self::assertSame('t1', $issued->key->tenantId);
        self::assertTrue($issued->key->active);
        self::assertSame(120, $issued->key->ratePerMinute);
        // Only the hash of the secret is stored; the key is found by that hash.
        self::assertSame($issued->key, $store->findKeyByHash(hash('sha256', $issued->secret)));
    }

    public function testEachIssueIsUnique(): void
    {
        $issuer = new KeyIssuer(new InMemoryKeyStore());
        $first = $issuer->issue('t1');
        $second = $issuer->issue('t1');

        self::assertNotSame($first->secret, $second->secret);
        self::assertNotSame($first->key->id, $second->key->id);
    }

    public function testRotateDeactivatesTheOldKeyAndIssuesANewOne(): void
    {
        $store = new InMemoryKeyStore();
        $issuer = new KeyIssuer($store);
        $first = $issuer->issue('t1', 'main', 90);

        $second = $issuer->rotate($first->key->id);

        $old = $store->findKey($first->key->id);
        self::assertNotNull($old);
        self::assertFalse($old->active);
        self::assertNotSame($first->key->id, $second->key->id);
        self::assertTrue($second->key->active);
        self::assertSame('t1', $second->key->tenantId);
        self::assertSame(90, $second->key->ratePerMinute);
    }

    public function testRevokeDeactivatesTheKey(): void
    {
        $store = new InMemoryKeyStore();
        $issuer = new KeyIssuer($store);
        $issued = $issuer->issue('t1');

        $issuer->revoke($issued->key->id);

        $revoked = $store->findKey($issued->key->id);
        self::assertNotNull($revoked);
        self::assertFalse($revoked->active);
    }

    public function testRotatingAnUnknownKeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new KeyIssuer(new InMemoryKeyStore()))->rotate('nope');
    }
}
