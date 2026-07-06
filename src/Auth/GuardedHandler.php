<?php

declare(strict_types=1);

namespace Directorium\Api\Auth;

use Directorium\Api\Http\Handler;
use Directorium\Api\Http\Request;
use Directorium\Api\Http\Response;

/**
 * Wraps a handler with the access gate (#24/#25/#26): every request is authorised
 * first — throwing 401/429 before any work — and the rate-limit headers from the
 * grant are echoed on the response. When access control is open the grant is empty,
 * so the wrapper is transparent.
 */
final class GuardedHandler implements Handler
{
    public function __construct(
        private readonly Handler $inner,
        private readonly AccessControl $access,
    ) {
    }

    public function handle(Request $request, array $params): Response
    {
        $grant = $this->access->authorise($request);

        return $this->inner->handle($request, $params)->withHeaders($grant->headers);
    }
}
