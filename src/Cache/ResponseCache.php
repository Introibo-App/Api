<?php

declare(strict_types=1);

namespace Introibo\Api\Cache;

use Introibo\Api\Http\Response;

/**
 * The read-through cache for calendar responses (#15, #16). A handler asks it to
 * respond for a key: on a store hit the pre-rendered bytes are returned untouched;
 * on a miss the body is computed, written back to the {@see StaticStore} (so the
 * next request — and the edge — is served from the static tier), and returned.
 * Either way the response carries cache-friendly headers so a CDN can hold it.
 *
 * The header set here is deliberately conservative; the ETag + immutable,
 * version-keyed `Cache-Control` land with the caching epic (#17).
 */
final class ResponseCache
{
    private const CACHE_CONTROL = 'public, max-age=3600';

    public function __construct(private readonly StaticStore $store)
    {
    }

    /**
     * @param callable(): array<string, mixed> $compute Builds the response envelope on a miss.
     */
    public function respond(string $key, callable $compute): Response
    {
        $cached = $this->store->get($key);
        if ($cached !== null) {
            $response = new Response(200, $cached, ['content-type' => 'application/json; charset=utf-8']);

            return $this->decorate($response, 'HIT');
        }

        $response = Response::json($compute());
        if ($response->status === 200) {
            $this->store->put($key, $response->body);
        }

        return $this->decorate($response, 'MISS');
    }

    private function decorate(Response $response, string $state): Response
    {
        return $response
            ->withHeader('cache-control', self::CACHE_CONTROL)
            ->withHeader('x-cache', $state);
    }
}
