<?php

declare(strict_types=1);

namespace Directorium\Api\Handler;

use Directorium\Api\Admin\AdminGate;
use Directorium\Api\Contract\Envelope;
use Directorium\Api\Edge\EdgeCache;
use Directorium\Api\Engine\CoreGateway;
use Directorium\Api\Http\Handler;
use Directorium\Api\Http\Request;
use Directorium\Api\Http\Response;

/**
 * `POST /v1/admin/rebuild` (#28) — the admin action that ties a data-version change to
 * cache invalidation: it reports the current data version and purges the edge, so the
 * new build is served at once. The static tier itself is regenerated out of band with
 * `bin/generate-static.php` for the reported version (a build step); this action is
 * the coordination point.
 */
final class RebuildHandler implements Handler
{
    public function __construct(
        private readonly AdminGate $gate,
        private readonly EdgeCache $edge,
        private readonly CoreGateway $core,
    ) {
    }

    public function handle(Request $request, array $params): Response
    {
        $this->gate->authorise($request);
        $this->edge->purgeAll();

        $data = [
            'dataVersion' => $this->core->dataVersion(),
            'purged' => true,
            'staticGeneration' => 'Run bin/generate-static.php for this data version to warm the static tier.',
        ];

        return Response::json(
            Envelope::of($data, Envelope::meta($this->core->dataVersion())),
        )->withHeader('cache-control', 'no-store');
    }
}
