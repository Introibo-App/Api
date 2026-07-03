<?php

declare(strict_types=1);

namespace Introibo\Api\Auth;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The real clock: the current UTC instant.
 */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
