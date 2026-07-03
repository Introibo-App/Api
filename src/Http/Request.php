<?php

declare(strict_types=1);

namespace Introibo\Api\Http;

/**
 * An immutable HTTP request: the method, decoded path, query parameters, and a
 * lower-cased header map. The service reads everything it needs from these — it
 * never touches superglobals past {@see fromGlobals()}, so handlers are trivially
 * testable with a hand-built request.
 */
final readonly class Request
{
    /**
     * @param array<string, string> $query   Query parameters, already url-decoded.
     * @param array<string, string> $headers Header name (lower-case) => value.
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $headers = [],
    ) {
    }

    /**
     * Build a request from PHP's SAPI globals. The path is the URL path only
     * (query string stripped); a trailing slash is normalised away so `/v1/day/`
     * and `/v1/day` route alike, except for the root path.
     */
    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $rawUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($rawUri, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';

        return new self($method, self::normalisePath($path), self::globalQuery(), self::globalHeaders());
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    private static function normalisePath(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        $trimmed = rtrim($path, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    /**
     * @return array<string, string>
     */
    private static function globalQuery(): array
    {
        $query = [];
        /** @var mixed $value */
        foreach ($_GET as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    private static function globalHeaders(): array
    {
        $headers = [];
        /** @var mixed $value */
        foreach ($_SERVER as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
