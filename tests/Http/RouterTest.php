<?php

declare(strict_types=1);

namespace Directorium\Api\Tests\Http;

use Closure;
use Directorium\Api\Contract\ApiException;
use Directorium\Api\Http\Handler;
use Directorium\Api\Http\Request;
use Directorium\Api\Http\Response;
use Directorium\Api\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testMatchesRouteAndExtractsPathParameter(): void
    {
        $router = new Router();
        $router->add('GET', '/v1/day/{date}', $this->handler(
            static fn (Request $request, array $params): Response => Response::json(['date' => $params['date']]),
        ));

        $response = $router->dispatch(new Request('GET', '/v1/day/2026-09-15'));

        self::assertSame(200, $response->status);
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('2026-09-15', $decoded['date']);
    }

    public function testUrlEncodedParameterIsDecoded(): void
    {
        $router = new Router();
        $router->add('GET', '/v1/echo/{value}', $this->handler(
            static fn (Request $request, array $params): Response => Response::json(['value' => $params['value']]),
        ));

        $response = $router->dispatch(new Request('GET', '/v1/echo/a%20b'));

        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('a b', $decoded['value']);
    }

    public function testUnknownPathThrowsNotFound(): void
    {
        $router = new Router();
        $router->add('GET', '/v1/day/{date}', $this->handler(static fn (): Response => Response::json([])));

        try {
            $router->dispatch(new Request('GET', '/v1/nope'));
            self::fail('Expected a NOT_FOUND ApiException.');
        } catch (ApiException $e) {
            self::assertSame('not_found', $e->error()->code);
            self::assertSame(404, $e->error()->status);
        }
    }

    public function testMatchingPathWrongMethodThrowsMethodNotAllowed(): void
    {
        $router = new Router();
        $router->add('GET', '/v1/day/{date}', $this->handler(static fn (): Response => Response::json([])));

        try {
            $router->dispatch(new Request('POST', '/v1/day/2026-09-15'));
            self::fail('Expected a METHOD_NOT_ALLOWED ApiException.');
        } catch (ApiException $e) {
            self::assertSame('method_not_allowed', $e->error()->code);
            self::assertSame(405, $e->error()->status);
        }
    }

    private function handler(Closure $fn): Handler
    {
        return new class ($fn) implements Handler {
            public function __construct(private readonly Closure $fn)
            {
            }

            public function handle(Request $request, array $params): Response
            {
                return ($this->fn)($request, $params);
            }
        };
    }
}
