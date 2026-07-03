<?php

declare(strict_types=1);

namespace Introibo\Api\Handler;

use Introibo\Api\Contract\Envelope;
use Introibo\Api\Engine\CoreGateway;
use Introibo\Api\Http\Handler;
use Introibo\Api\Http\Request;
use Introibo\Api\Http\Response;

/**
 * `GET /v1/meta` — the discovery endpoint (#12): the systems, calendars, languages,
 * and date range the service supports, every value derived from Core rather than
 * hardcoded, so clients stay in sync as later milestones add systems and calendars.
 */
final class MetaHandler implements Handler
{
    public function __construct(private readonly CoreGateway $core)
    {
    }

    public function handle(Request $request, array $params): Response
    {
        $data = [
            'systems' => $this->core->systemsDetail(),
            'calendars' => $this->core->calendarsDetail(),
            'languages' => $this->core->languages(),
            'range' => [
                'minYear' => CoreGateway::MIN_YEAR,
                'maxYear' => CoreGateway::MAX_YEAR,
            ],
        ];

        return Response::json(Envelope::of($data, Envelope::meta($this->core->dataVersion())));
    }
}
