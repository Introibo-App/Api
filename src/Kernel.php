<?php

declare(strict_types=1);

namespace Directorium\Api;

use Directorium\Api\Admin\AdminGate;
use Directorium\Api\Auth\AccessControl;
use Directorium\Api\Auth\GuardedHandler;
use Directorium\Api\Cache\ResponseCache;
use Directorium\Api\Cache\StaticStore;
use Directorium\Api\Contract\ApiError;
use Directorium\Api\Contract\ApiException;
use Directorium\Api\Edge\CloudflareEdge;
use Directorium\Api\Edge\EdgeCache;
use Directorium\Api\Edge\NullEdge;
use Directorium\Api\Engine\CoreGateway;
use Directorium\Api\Handler\DayHandler;
use Directorium\Api\Handler\HealthHandler;
use Directorium\Api\Handler\MetaHandler;
use Directorium\Api\Handler\MonthHandler;
use Directorium\Api\Handler\PolicyHandler;
use Directorium\Api\Handler\PurgeHandler;
use Directorium\Api\Handler\RebuildHandler;
use Directorium\Api\Handler\YearHandler;
use Directorium\Api\Http\Handler;
use Directorium\Api\Http\Request;
use Directorium\Api\Http\Response;
use Directorium\Api\Http\Router;
use Directorium\Api\Legal\Policies;
use Directorium\Api\Query\QueryParser;
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
        ?AdminGate $admin = null,
        ?EdgeCache $edge = null,
    ) {
        $core ??= new CoreGateway();
        $this->dataVersion = $core->dataVersion();
        $store ??= StaticStore::fromEnvironment($this->dataVersion);
        $access ??= AccessControl::fromEnvironment();
        $admin ??= AdminGate::fromEnvironment();
        $edge ??= CloudflareEdge::fromEnvironment() ?? new NullEdge();
        $parser = new QueryParser($core);
        $cache = new ResponseCache($store);

        // Health, discovery, and the policies are public; the metered calendar reads
        // sit behind the access gate (a no-op when access control is disabled).
        $guard = static fn (Handler $handler): Handler => new GuardedHandler($handler, $access);

        $this->router = new Router();
        $this->router->add('GET', '/v1/health', new HealthHandler($core));
        $this->router->add('GET', '/v1/meta', new MetaHandler($core));
        $this->router->add('GET', '/v1/aup', new PolicyHandler(Policies::aup()));
        $this->router->add('GET', '/v1/terms', new PolicyHandler(Policies::terms()));
        $this->router->add('GET', '/v1/day/{date}', $guard(new DayHandler($core, $parser, $cache)));
        $this->router->add('GET', '/v1/month/{month}', $guard(new MonthHandler($core, $parser, $cache)));
        $this->router->add('GET', '/v1/year/{year}', $guard(new YearHandler($core, $parser, $cache)));

        // Admin actions exist only when an admin token is configured — an unconfigured
        // service exposes no admin surface at all.
        if ($admin->enabled()) {
            $this->router->add('POST', '/v1/admin/purge', new PurgeHandler($admin, $edge, $core));
            $this->router->add('POST', '/v1/admin/rebuild', new RebuildHandler($admin, $edge, $core));
        }
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
