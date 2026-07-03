<?php

declare(strict_types=1);

namespace Introibo\Api\Handler;

use Introibo\Api\Http\Request;
use Introibo\Api\Query\RangeQuery;

/**
 * `GET /v1/month/{year-month}` — every resolved liturgical day in a month, as an
 * ordered list of day contracts. The whole civil year is resolved once behind the
 * gateway, so the month is a slice of it.
 */
final class MonthHandler extends RangeHandler
{
    protected function parse(array $params, Request $request): RangeQuery
    {
        return $this->parser->parseMonth($params['month'] ?? '', $request);
    }
}
