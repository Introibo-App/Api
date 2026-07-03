<?php

declare(strict_types=1);

namespace Introibo\Api\Handler;

use Introibo\Api\Http\Request;
use Introibo\Api\Query\RangeQuery;

/**
 * `GET /v1/year/{year}` — every resolved liturgical day in a civil year, as an
 * ordered list of day contracts, from a single Core resolution.
 */
final class YearHandler extends RangeHandler
{
    protected function parse(array $params, Request $request): RangeQuery
    {
        return $this->parser->parseYear($params['year'] ?? '', $request);
    }
}
