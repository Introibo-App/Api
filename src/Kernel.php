<?php

declare(strict_types=1);

namespace Introibo\Api;

use Introibo\Api\Cache\ResponseCache;
use Introibo\Api\Cache\StaticStore;
use Introibo\Api\Contract\ApiError;
use Introibo\Api\Contract\ApiException;
use Introibo\Api\Engine\CoreGateway;
use Introibo\Api\Handler\DayHandler;
use Introibo\Api\Handler\HealthHandler;
use Introibo\Api\Handler\MetaHandler;
use Introibo\Api\Handler\MonthHandler;
use Introibo\Api\Handler\YearHandler;
use Introibo\Api\Http\Request;
use Introibo\Api\Http\Response;
use Introibo\Api\Http\Router;
use Introibo\Api\Query\QueryParser;
use Throwable;

/**
 * The application: it wires the Core gateway, the request parser, the static-cache
 * tier, and the routes, then turns a request into a response. It is the one place
 * that converts a failure into the canonical error envelope — a known
 * {@see ApiException} at its own status, and anything unexpected into an opaque
 * 500, so internal detail never escapes.
 */
final class Kernel
{
    private readonly Router $router;

    public function __construct(?CoreGateway $core = null, ?StaticStore $store = null)
    {
        $core ??= new CoreGateway();
        $store ??= StaticStore::fromEnvironment($core->dataVersion());
        $parser = new QueryParser($core);
        $cache = new ResponseCache($store);

        $this->router = new Router();
        $this->router->add('GET', '/v1/health', new HealthHandler($core));
        $this->router->add('GET', '/v1/meta', new MetaHandler($core));
        $this->router->add('GET', '/v1/day/{date}', new DayHandler($core, $parser, $cache));
        $this->router->add('GET', '/v1/month/{month}', new MonthHandler($core, $parser, $cache));
        $this->router->add('GET', '/v1/year/{year}', new YearHandler($core, $parser, $cache));
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (ApiException $e) {
            return Response::error($e->error());
        } catch (Throwable) {
            return Response::error(ApiError::internal());
        }
    }
}
