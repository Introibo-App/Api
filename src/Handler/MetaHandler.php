<?php

declare(strict_types=1);

namespace Directorium\Api\Handler;

use Directorium\Api\Cache\CacheHeaders;
use Directorium\Api\Contract\Envelope;
use Directorium\Api\Engine\CoreGateway;
use Directorium\Api\Http\Handler;
use Directorium\Api\Http\Request;
use Directorium\Api\Http\Response;

/**
 * `GET /v1/meta` — the discovery endpoint (#12): the systems, calendars, languages,
 * and date range the service supports, every value derived from Core rather than
 * hardcoded, so clients stay in sync as later milestones add systems and calendars.
 * It is cacheable like the calendar reads — it changes only with the data version —
 * so it carries the same ETag + immutable `Cache-Control` and honours conditional
 * GETs, but it is small and cheap enough not to need the static store.
 */
final class MetaHandler implements Handler
{
    private const KEY = 'v1/meta';

    public function __construct(private readonly CoreGateway $core)
    {
    }

    public function handle(Request $request, array $params): Response
    {
        $etag = CacheHeaders::etag($this->core->dataVersion(), self::KEY);

        $notModified = CacheHeaders::notModified($request, $etag);
        if ($notModified !== null) {
            return $notModified;
        }

        $data = [
            'systems' => $this->core->systemsDetail(),
            'calendars' => $this->core->calendarsDetail(),
            'languages' => $this->core->languages(),
            'range' => [
                'minYear' => CoreGateway::MIN_YEAR,
                'maxYear' => CoreGateway::MAX_YEAR,
            ],
        ];
        $response = Response::json(Envelope::of($data, Envelope::meta($this->core->dataVersion())));

        return CacheHeaders::apply($response, $etag);
    }
}
