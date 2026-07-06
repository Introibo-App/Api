<?php

declare(strict_types=1);

namespace Directorium\Api\Auth;

use DateTimeImmutable;

/**
 * The current instant, injected so quota periods and rate-limit windows are
 * deterministic under test.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
