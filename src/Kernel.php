<?php

declare(strict_types=1);

namespace Introibo\Api;

use Introibo\Api\Auth\AccessControl;
use Introibo\Api\Auth\GuardedHandler;
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
use Introibo\Api\Http\Handler;
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

    private readonly string $dataVersion;

    public function __construct(
        ?CoreGateway $core = null,
        ?StaticStore $store = null,
        ?AccessControl $access = null,
    ) {
        $core ??= new CoreGateway();
        $this->dataVersion = $core->dataVersion();
        $store ??= StaticStore::fromEnvironment($this->dataVersion);
        $access ??= AccessControl::fromEnvironment();
        $parser = new QueryParser($core);
        $cache = new ResponseCache($store);

        // Health and discovery are public; the metered calendar reads sit behind the
        // access gate (a no-op when access control is disabled).
        $guard = static fn (Handler $handler): Handler => new GuardedHandler($handler, $access);

        $this->router = new Router();
        $this->router->add('GET', '/v1/health', new HealthHandler($core));
        $this->router->add('GET', '/v1/meta', new MetaHandler($core));
        $this->router->add('GET', '/v1/day/{date}', $guard(new DayHandler($core, $parser, $cache)));
        $this->router->add('GET', '/v1/month/{month}', $guard(new MonthHandler($core, $parser, $cache)));
        $this->router->add('GET', '/v1/year/{year}', $guard(new YearHandler($core, $parser, $cache)));
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->router->dispatch($request);
        } catch (ApiException $e) {
            $response = Response::error($e->error())->withHeaders($e->headers());
        } catch (Throwable) {
            $response = Response::error(ApiError::internal());
        }

        // The service-wide data-version stamp on every response (#19), so any client
        // or proxy can read which build produced it without parsing the body.
        return $response->withHeader('x-data-version', $this->dataVersion);
    }
}
