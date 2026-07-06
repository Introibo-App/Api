<?php

declare(strict_types=1);

namespace Directorium\Api\Tests\Cache;

use Directorium\Api\Cache\StaticStore;
use Directorium\Api\Tests\Support\TempDir;
use PHPUnit\Framework\TestCase;

final class StaticStoreTest extends TestCase
{
    use TempDir;

    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->makeTempDir();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testPutThenGetRoundTrips(): void
    {
        $store = new StaticStore($this->root, 'v1');
        $key = 'v1/day/2026-09-03/1962/sspx';

        self::assertTrue($store->enabled());
        self::assertNull($store->get($key));

        $store->put($key, '{"ok":true}');
        self::assertSame('{"ok":true}', $store->get($key));
    }

    public function testDisabledStoreReadsMissAndWritesNoop(): void
    {
        $store = new StaticStore(null, 'v1');

        self::assertFalse($store->enabled());
        $store->put('k', 'body');
        self::assertNull($store->get('k'));
    }

    public function testDifferentDataVersionsAreIsolated(): void
    {
        (new StaticStore($this->root, 'A'))->put('k', 'first');

        $second = new StaticStore($this->root, 'B');
        self::assertNull($second->get('k'), 'A new data version must not see the old version files.');

        $second->put('k', 'second');
        self::assertSame('first', (new StaticStore($this->root, 'A'))->get('k'));
        self::assertSame('second', (new StaticStore($this->root, 'B'))->get('k'));
    }

    public function testSanitisesTheVersionIntoASafeDirectory(): void
    {
        $store = new StaticStore($this->root, 'c1.0.0+e0.4.0+d1962:2026-07-02');
        $store->put('v1/day/2026-09-03/1962/universal', 'x');

        self::assertSame('x', $store->get('v1/day/2026-09-03/1962/universal'));
    }
}
