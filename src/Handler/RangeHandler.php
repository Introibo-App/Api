<?php

declare(strict_types=1);

namespace Directorium\Api\Handler;

use Directorium\Api\Cache\ResponseCache;
use Directorium\Api\Contract\Envelope;
use Directorium\Api\Engine\CoreGateway;
use Directorium\Api\Http\Handler;
use Directorium\Api\Http\Request;
use Directorium\Api\Http\Response;
use Directorium\Api\Query\QueryParser;
use Directorium\Api\Query\RangeQuery;

/**
 * Shared machinery for the range endpoints (a month, a whole year): parse the span,
 * resolve every day off a single Core resolution, and return the ordered list with
 * a `count` in `meta`, through the read-through cache. Subclasses only say how to
 * parse their path segment.
 */
abstract class RangeHandler implements Handler
{
    public function __construct(
        protected readonly CoreGateway $core,
        protected readonly QueryParser $parser,
        protected readonly ResponseCache $cache,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    abstract protected function parse(array $params, Request $request): RangeQuery;

    public function handle(Request $request, array $params): Response
    {
        $query = $this->parse($params, $request);

        return $this->cache->respond(
            $request,
            $query->cacheKey(),
            $this->core->dataVersion(),
            function () use ($query): array {
                $days = $this->core->days($query);
                $meta = array_merge(
                    Envelope::meta($this->core->dataVersion(), $query->parameters()),
                    ['count' => count($days)],
                );

                return Envelope::of($days, $meta);
            },
        );
    }
}
