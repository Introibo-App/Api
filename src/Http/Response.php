<?php

declare(strict_types=1);

namespace Introibo\Api\Http;

use Introibo\Api\Contract\ApiError;
use Introibo\Api\Contract\Json;

/**
 * An immutable HTTP response: a status code, a header map, and an already-encoded
 * body. Handlers return one of these; the front controller {@see send()}s it. The
 * response never mutates in place — {@see withHeader()} returns a copy — so the
 * caching layer can decorate a response without a handler knowing.
 */
final readonly class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status,
        public string $body,
        public array $headers = [],
    ) {
    }

    /**
     * A JSON response with the frozen encoding flags and a `application/json`
     * content type. The body is the canonical serialisation of `$payload`.
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public static function json(array $payload, int $status = 200, array $headers = []): self
    {
        return new self(
            $status,
            Json::encode($payload),
            ['content-type' => 'application/json; charset=utf-8'] + $headers,
        );
    }

    /**
     * The canonical error response: the single error envelope at the error's own
     * HTTP status.
     */
    public static function error(ApiError $error): self
    {
        return self::json($error->payload(), $error->status);
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self($this->status, $this->body, $headers + $this->headers);
    }

    public function withHeader(string $name, string $value): self
    {
        return $this->withHeaders([strtolower($name) => $value]);
    }

    /**
     * Emit the response to the SAPI. Kept side-effect-only and untested; all logic
     * lives in the immutable value above.
     *
     * @codeCoverageIgnore
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo $this->body;
    }
}
