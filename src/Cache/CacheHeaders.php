<?php

declare(strict_types=1);

namespace Directorium\Api\Cache;

use Directorium\Api\Http\Request;
use Directorium\Api\Http\Response;

/**
 * The HTTP-caching semantics for cacheable responses (#18): a strong ETag, an
 * immutable, edge-friendly `Cache-Control`, and conditional-GET handling.
 *
 * A calendar response is a pure function of its request key and the data version, so
 * the ETag is derived from exactly those two — it changes precisely when the content
 * would, and never needs the (possibly large) body hashed. A client that already
 * holds the current ETag gets a bodiless `304 Not Modified`.
 *
 * `Cache-Control` marks responses cacheable for a long time at the edge (a week) and
 * a modest time in the browser, `immutable` because content never changes for a
 * given data version. A correction bumps the data version and purges the edge (#28),
 * so the new version is served at once rather than waiting out a TTL.
 */
final class CacheHeaders
{
    public const CONTROL = 'public, max-age=3600, s-maxage=604800, immutable';

    /** The strong ETag for content addressed by `$key` at data version `$dataVersion`. */
    public static function etag(string $dataVersion, string $key): string
    {
        return '"' . substr(hash('sha256', $dataVersion . '|' . $key), 0, 20) . '"';
    }

    /**
     * A `304 Not Modified` when the request already holds this ETag, else null so the
     * caller serves the full response.
     */
    public static function notModified(Request $request, string $etag): ?Response
    {
        $header = $request->header('if-none-match');
        if ($header === null) {
            return null;
        }

        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);
            $normalised = str_starts_with($candidate, 'W/') ? substr($candidate, 2) : $candidate;
            if ($candidate === '*' || $normalised === $etag) {
                return new Response(304, '', ['etag' => $etag, 'cache-control' => self::CONTROL]);
            }
        }

        return null;
    }

    /** Stamp a cacheable response with its ETag, `Cache-Control`, and cache state. */
    public static function apply(Response $response, string $etag, ?string $cacheState = null): Response
    {
        $response = $response
            ->withHeader('etag', $etag)
            ->withHeader('cache-control', self::CONTROL);

        return $cacheState === null ? $response : $response->withHeader('x-cache', $cacheState);
    }
}
