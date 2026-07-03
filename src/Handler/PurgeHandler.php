<?php

declare(strict_types=1);

namespace Introibo\Api\Handler;

use Introibo\Api\Admin\AdminGate;
use Introibo\Api\Contract\Envelope;
use Introibo\Api\Edge\EdgeCache;
use Introibo\Api\Engine\CoreGateway;
use Introibo\Api\Http\Handler;
use Introibo\Api\Http\Request;
use Introibo\Api\Http\Response;

/**
 * `POST /v1/admin/purge` (#29) — an admin-only action that purges the edge cache.
 * Used on its own to invalidate the CDN without a full rebuild.
 */
final class PurgeHandler implements Handler
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

        return Response::json(
            Envelope::of(['purged' => true], Envelope::meta($this->core->dataVersion())),
        )->withHeader('cache-control', 'no-store');
    }
}
