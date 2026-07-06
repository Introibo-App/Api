<?php

declare(strict_types=1);

namespace Directorium\Api\Tests\Support;

use DateTimeImmutable;
use Directorium\Api\Auth\Clock;

/**
 * A clock frozen at a chosen instant, so quota periods and rate-limit windows are
 * deterministic under test.
 */
final class FixedClock implements Clock
{
    public function __construct(private readonly DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
