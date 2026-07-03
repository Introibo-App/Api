<?php

declare(strict_types=1);

namespace Introibo\Api;

use Introibo\Api\Contract\ApiError;
use Introibo\Api\Contract\ApiException;
use Introibo\Api\Engine\CoreGateway;
use Introibo\Api\Handler\DayHandler;
use Introibo\Api\Handler\HealthHandler;
use Introibo\Api\Http\Request;
use Introibo\Api\Http\Response;
use Introibo\Api\Http\Router;
use Introibo\Api\Query\QueryParser;
use Throwable;

/**
 * The application: it wires the Core gateway, the request parser, and the routes,
 * then turns a request into a response. It is the one place that converts a failure
 * into the canonical error envelope — a known {@see ApiException} at its own status,
 * and anything unexpected into an opaque 500, so internal detail never escapes.
 */
final class Kernel
{
    private readonly Router $router;

    public function __construct(?CoreGateway $core = null)
    {
        $core ??= new CoreGateway();
        $parser = new QueryParser($core);

        $this->router = new Router();
        $this->router->add('GET', '/v1/health', new HealthHandler($core));
        $this->router->add('GET', '/v1/day/{date}', new DayHandler($core, $parser));
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
