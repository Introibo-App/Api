<?php

declare(strict_types=1);

namespace Directorium\Api\Handler;

use Directorium\Api\Http\Request;
use Directorium\Api\Query\RangeQuery;

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
