<?php

declare(strict_types=1);

namespace Introibo\Api\Handler;

use Introibo\Api\Contract\Envelope;
use Introibo\Api\Engine\CoreGateway;
use Introibo\Api\Http\Handler;
use Introibo\Api\Http\Request;
use Introibo\Api\Http\Response;
use Introibo\Api\Query\QueryParser;

/**
 * `GET /v1/day/{date}` — the single most-requested read and the service's anchor:
 * the resolved liturgical day for a date, under a chosen system, calendar, and
 * language. The handler validates the request and hands off to the Core gateway; it
 * holds no calendar logic of its own.
 */
final class DayHandler implements Handler
{
    public function __construct(
        private readonly CoreGateway $core,
        private readonly QueryParser $parser,
    ) {
    }

    public function handle(Request $request, array $params): Response
    {
        $query = $this->parser->parseDay($params['date'] ?? '', $request);
        $day = $this->core->day($query);

        return Response::json(
            Envelope::of($day, Envelope::meta($this->core->dataVersion(), $query->parameters())),
        );
    }
}
