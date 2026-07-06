<?php

declare(strict_types=1);

namespace Directorium\Api\Cache;

use Directorium\Api\Http\Request;
use Directorium\Api\Http\Response;

/**
 * The read-through cache for calendar responses (#15, #16, #18). A handler asks it to
 * respond for a key at the current data version:
 *
 *   1. If the request already holds the current ETag, return a bodiless 304.
 *   2. On a store hit, return the pre-rendered bytes untouched.
 *   3. On a miss, compute the body, write it back to the {@see StaticStore} (so the
 *      next request — and the edge — is served from the static tier), and return it.
 *
 * Every served response carries the strong ETag and immutable `Cache-Control` from
 * {@see CacheHeaders}, plus an `x-cache` state for observability.
 */
final class ResponseCache
{
    public function __construct(private readonly StaticStore $store)
    {
    }

    /**
     * @param callable(): array<string, mixed> $compute Builds the response envelope on a miss.
     */
    public function respond(Request $request, string $key, string $dataVersion, callable $compute): Response
    {
        $etag = CacheHeaders::etag($dataVersion, $key);

        $notModified = CacheHeaders::notModified($request, $etag);
        if ($notModified !== null) {
            return $notModified;
        }

        $cached = $this->store->get($key);
        if ($cached !== null) {
            $hit = new Response(200, $cached, ['content-type' => 'application/json; charset=utf-8']);

            return CacheHeaders::apply($hit, $etag, 'HIT');
        }

        $response = Response::json($compute());
        if ($response->status === 200) {
            $this->store->put($key, $response->body);
        }

        return CacheHeaders::apply($response, $etag, 'MISS');
    }
}
