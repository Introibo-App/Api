<?php

declare(strict_types=1);

namespace Introibo\Api\Tests\Support;

use Introibo\Api\Edge\EdgeCache;

/**
 * An {@see EdgeCache} double that counts purges, so admin actions can be asserted
 * without a real CDN.
 */
final class RecordingEdge implements EdgeCache
{
    public int $purges = 0;

    public function purgeAll(): void
    {
        $this->purges++;
    }
}
