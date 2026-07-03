<?php

declare(strict_types=1);

namespace Introibo\Api\Handler;

use Introibo\Api\Contract\Envelope;
use Introibo\Api\Engine\CoreGateway;
use Introibo\Api\Http\Handler;
use Introibo\Api\Http\Request;
use Introibo\Api\Http\Response;

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
                ['status' => 'ok', 'service' => 'introibo-api'],
                Envelope::meta($this->core->dataVersion()),
            ),
        )->withHeader('cache-control', 'no-store');
    }
}
