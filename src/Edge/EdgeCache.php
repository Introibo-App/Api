<?php

declare(strict_types=1);

namespace Directorium\Api\Edge;

/**
 * The edge (CDN) cache the origin can purge (#29). The service depends on this seam;
 * production wires {@see CloudflareEdge}, and when no edge is configured
 * {@see NullEdge} makes a purge a no-op.
 */
interface EdgeCache
{
    /** Purge everything the edge holds — used after a data-version bump. */
    public function purgeAll(): void;
}
