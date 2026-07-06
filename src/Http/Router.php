<?php

declare(strict_types=1);

namespace Directorium\Api\Http;

use Directorium\Api\Contract\ApiException;
use Directorium\Api\Contract\ErrorCode;

/**
 * A tiny path router. Routes are literal paths with `{name}` placeholders that
 * capture a single non-slash segment (e.g. `/v1/day/{date}`). Matching is exact
 * and ordered; a path that matches but with the wrong method yields 405, a path
 * that matches nothing yields 404 — both as the canonical error envelope.
 *
 * Handlers key off the resolved parameters, never off the URL shape, so a future
 * resource-oriented layout (e.g. `/v1/calendars/{calendar}/days/{date}`) is added
 * as an extra route to the same handler with no client breakage.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: Handler}> */
    private array $routes = [];

    public function add(string $method, string $pattern, Handler $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => self::compile($pattern),
            'handler' => $handler,
        ];
    }

    /**
     * Resolve the request to a handler and run it. Throws {@see ApiException} with
     * NOT_FOUND or METHOD_NOT_ALLOWED so the kernel renders a uniform error.
     */
    public function dispatch(Request $request): Response
    {
        $pathMatched = false;
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] === $request->method) {
                return $route['handler']->handle($request, self::params($matches));
            }
        }

        throw ApiException::of($pathMatched ? ErrorCode::METHOD_NOT_ALLOWED : ErrorCode::NOT_FOUND);
    }

    /**
     * Compile a route pattern into an anchored regex, escaping the literal parts
     * and turning each `{name}` into a named single-segment capture.
     */
    private static function compile(string $pattern): string
    {
        $regex = '';
        $offset = 0;
        if (preg_match_all('/\{([a-z][a-z0-9]*)\}/', $pattern, $tokens, PREG_OFFSET_CAPTURE) >= 1) {
            foreach ($tokens[0] as $index => $whole) {
                $literal = substr($pattern, $offset, (int) $whole[1] - $offset);
                $regex .= preg_quote($literal, '#');
                $regex .= '(?P<' . $tokens[1][$index][0] . '>[^/]+)';
                $offset = (int) $whole[1] + strlen((string) $whole[0]);
            }
        }
        $regex .= preg_quote(substr($pattern, $offset), '#');

        return '#^' . $regex . '$#';
    }

    /**
     * The named captures from a successful match, url-decoded.
     *
     * @param array<int|string, string> $matches
     * @return array<string, string>
     */
    private static function params(array $matches): array
    {
        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = rawurldecode($value);
            }
        }

        return $params;
    }
}
