<?php

declare(strict_types=1);

namespace Directorium\Api\Edge;

/**
 * The no-op edge: used when no CDN is configured, so the admin actions run without an
 * external dependency (a purge simply does nothing).
 */
final class NullEdge implements EdgeCache
{
    public function purgeAll(): void
    {
    }
}
