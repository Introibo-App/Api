<?php

declare(strict_types=1);

namespace Introibo\Api\Tests\Cache;

use Introibo\Api\Cache\ResponseCache;
use Introibo\Api\Cache\StaticStore;
use Introibo\Api\Tests\Support\TempDir;
use PHPUnit\Framework\TestCase;

final class ResponseCacheTest extends TestCase
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

    public function testMissComputesWritesAndTagsTheResponse(): void
    {
        $cache = new ResponseCache(new StaticStore($this->root, 'v'));
        $calls = 0;

        $response = $cache->respond('k', function () use (&$calls): array {
            $calls++;

            return ['data' => ['n' => 1], 'meta' => []];
        });

        self::assertSame(200, $response->status);
        self::assertSame('MISS', $response->headers['x-cache']);
        self::assertArrayHasKey('cache-control', $response->headers);
        self::assertSame(1, $calls);
    }

    public function testHitReturnsStoredBytesWithoutRecomputing(): void
    {
        $store = new StaticStore($this->root, 'v');
        $cache = new ResponseCache($store);
        $cache->respond('k', static fn (): array => ['data' => ['n' => 1], 'meta' => []]);

        $calls = 0;
        $response = $cache->respond('k', function () use (&$calls): array {
            $calls++;

            return ['data' => ['n' => 99], 'meta' => []];
        });

        self::assertSame('HIT', $response->headers['x-cache']);
        self::assertSame(0, $calls, 'A hit must not recompute.');
        self::assertStringContainsString('"n":1', $response->body);
    }

    public function testDisabledStoreAlwaysComputes(): void
    {
        $cache = new ResponseCache(new StaticStore(null, 'v'));
        $calls = 0;
        $compute = function () use (&$calls): array {
            $calls++;

            return ['data' => [], 'meta' => []];
        };

        $cache->respond('k', $compute);
        $response = $cache->respond('k', $compute);

        self::assertSame(2, $calls);
        self::assertSame('MISS', $response->headers['x-cache']);
    }
}
