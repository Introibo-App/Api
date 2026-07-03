<?php

declare(strict_types=1);

namespace Introibo\Api\Handler;

use Introibo\Api\Contract\Envelope;
use Introibo\Api\Engine\CoreGateway;
use Introibo\Api\Http\Handler;
use Introibo\Api\Http\Request;
use Introibo\Api\Http\Response;
use Introibo\Api\Query\QueryParser;
use Introibo\Api\Query\RangeQuery;

/**
 * Shared machinery for the range endpoints (a month, a whole year): parse the span,
 * resolve every day off a single Core resolution, and return the ordered list with
 * a `count` in `meta`. Subclasses only say how to read and parse their path segment.
 */
abstract class RangeHandler implements Handler
{
    public function __construct(
        protected readonly CoreGateway $core,
        protected readonly QueryParser $parser,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    abstract protected function parse(array $params, Request $request): RangeQuery;

    public function handle(Request $request, array $params): Response
    {
        $query = $this->parse($params, $request);
        $days = $this->core->days($query);
        $meta = array_merge(
            Envelope::meta($this->core->dataVersion(), $query->parameters()),
            ['count' => count($days)],
        );

        return Response::json(Envelope::of($days, $meta));
    }
}
