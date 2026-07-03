<?php

declare(strict_types=1);

namespace Introibo\Api\Tests\Cache;

use Introibo\Api\Cache\CacheHeaders;
use Introibo\Api\Http\Request;
use Introibo\Api\Http\Response;
use PHPUnit\Framework\TestCase;

final class CacheHeadersTest extends TestCase
{
    public function testEtagIsStableStrongAndQuoted(): void
    {
        $key = 'v1/day/2026-09-03/1962/sspx';
        $etag = CacheHeaders::etag('data-v', $key);

        self::assertSame($etag, CacheHeaders::etag('data-v', $key));
        self::assertStringStartsWith('"', $etag);
        self::assertStringEndsWith('"', $etag);
    }

    public function testEtagChangesWithDataVersionAndKey(): void
    {
        self::assertNotSame(CacheHeaders::etag('v1', 'k'), CacheHeaders::etag('v2', 'k'));
        self::assertNotSame(CacheHeaders::etag('v', 'a'), CacheHeaders::etag('v', 'b'));
    }

    public function testNotModifiedMatchesExactWeakAndStar(): void
    {
        $etag = CacheHeaders::etag('v', 'k');

        self::assertNotNull(CacheHeaders::notModified($this->ifNoneMatch($etag), $etag));
        self::assertNotNull(CacheHeaders::notModified($this->ifNoneMatch('W/' . $etag), $etag));
        self::assertNotNull(CacheHeaders::notModified($this->ifNoneMatch('*'), $etag));
        self::assertNotNull(CacheHeaders::notModified($this->ifNoneMatch('"other", ' . $etag), $etag));
    }

    public function testNotModifiedReturnsNullWhenNoMatch(): void
    {
        $etag = CacheHeaders::etag('v', 'k');

        self::assertNull(CacheHeaders::notModified($this->ifNoneMatch('"other"'), $etag));
        self::assertNull(CacheHeaders::notModified(new Request('GET', '/'), $etag));
    }

    public function testApplyStampsEtagCacheControlAndState(): void
    {
        $response = CacheHeaders::apply(new Response(200, 'body'), '"e"', 'HIT');

        self::assertSame('"e"', $response->headers['etag']);
        self::assertSame(CacheHeaders::CONTROL, $response->headers['cache-control']);
        self::assertSame('HIT', $response->headers['x-cache']);
        self::assertStringContainsString('immutable', $response->headers['cache-control']);
    }

    public function testApplyOmitsCacheStateWhenNull(): void
    {
        $response = CacheHeaders::apply(new Response(200, 'body'), '"e"');

        self::assertArrayNotHasKey('x-cache', $response->headers);
    }

    private function ifNoneMatch(string $value): Request
    {
        return new Request('GET', '/', [], ['if-none-match' => $value]);
    }
}
