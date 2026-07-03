<?php

declare(strict_types=1);

namespace Introibo\Api\Tests\Cache;

use Introibo\Api\Cache\CacheHeaders;
use Introibo\Api\Cache\ResponseCache;
use Introibo\Api\Cache\StaticStore;
use Introibo\Api\Http\Request;
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

        $response = $cache->respond($this->request(), 'k', 'v', function () use (&$calls): array {
            $calls++;

            return ['data' => ['n' => 1], 'meta' => []];
        });

        self::assertSame(200, $response->status);
        self::assertSame('MISS', $response->headers['x-cache']);
        self::assertArrayHasKey('cache-control', $response->headers);
        self::assertArrayHasKey('etag', $response->headers);
        self::assertSame(1, $calls);
    }

    public function testHitReturnsStoredBytesWithoutRecomputing(): void
    {
        $cache = new ResponseCache(new StaticStore($this->root, 'v'));
        $cache->respond($this->request(), 'k', 'v', static fn (): array => ['data' => ['n' => 1], 'meta' => []]);

        $calls = 0;
        $response = $cache->respond($this->request(), 'k', 'v', function () use (&$calls): array {
            $calls++;

            return ['data' => ['n' => 99], 'meta' => []];
        });

        self::assertSame('HIT', $response->headers['x-cache']);
        self::assertSame(0, $calls, 'A hit must not recompute.');
        self::assertStringContainsString('"n":1', $response->body);
    }

    public function testConditionalGetShortCircuitsTo304(): void
    {
        $cache = new ResponseCache(new StaticStore($this->root, 'v'));
        $etag = CacheHeaders::etag('v', 'k');
        $calls = 0;

        $response = $cache->respond(
            $this->request(['if-none-match' => $etag]),
            'k',
            'v',
            function () use (&$calls): array {
                $calls++;

                return ['data' => [], 'meta' => []];
            },
        );

        self::assertSame(304, $response->status);
        self::assertSame('', $response->body);
        self::assertSame($etag, $response->headers['etag']);
        self::assertSame(0, $calls, 'A 304 must not compute or touch the store.');
    }

    public function testDisabledStoreAlwaysComputes(): void
    {
        $cache = new ResponseCache(new StaticStore(null, 'v'));
        $calls = 0;
        $compute = function () use (&$calls): array {
            $calls++;

            return ['data' => [], 'meta' => []];
        };

        $cache->respond($this->request(), 'k', 'v', $compute);
        $response = $cache->respond($this->request(), 'k', 'v', $compute);

        self::assertSame(2, $calls);
        self::assertSame('MISS', $response->headers['x-cache']);
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(array $headers = []): Request
    {
        return new Request('GET', '/v1/day/x', [], $headers);
    }
}
