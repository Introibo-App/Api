<?php

declare(strict_types=1);

namespace Directorium\Api\Handler;

use Directorium\Api\Contract\Envelope;
use Directorium\Api\Engine\CoreGateway;
use Directorium\Api\Http\Handler;
use Directorium\Api\Http\Request;
use Directorium\Api\Http\Response;

/**
 * `GET /v1/health` — a liveness check that also proves the Core engine is wired and
 * loadable: it reports the service status and the live data-version stamp, which can
 * only be produced by resolving through Core. It is explicitly uncacheable, so a
 * probe always reaches the origin.
 */
final class HealthHandler implements Handler
{
    public function __construct(private readonly CoreGateway $core)
    {
    }

    public function handle(Request $request, array $params): Response
    {
        return Response::json(
            Envelope::of(
                ['status' => 'ok', 'service' => 'directorium-api'],
                Envelope::meta($this->core->dataVersion()),
            ),
        )->withHeader('cache-control', 'no-store');
    }
}
