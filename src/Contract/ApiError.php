<?php

declare(strict_types=1);

namespace Directorium\Api\Contract;

/**
 * A single, uniform error the whole service speaks. Every failing request — a bad
 * date, an unknown calendar, a missing route, an unexpected fault — becomes one of
 * these, so a client parses errors exactly one way. It serialises to
 * `{"error": {"code", "message", "status"}}`.
 */
final readonly class ApiError
{
    public string $code;
    public string $message;
    public int $status;

    private function __construct(ErrorCode $code, ?string $message)
    {
        $this->code = $code->value;
        $this->message = $message ?? $code->defaultMessage();
        $this->status = $code->status();
    }

    /**
     * Build an error from a code, optionally overriding the default message with a
     * more specific one (e.g. echoing the offending value).
     */
    public static function of(ErrorCode $code, ?string $message = null): self
    {
        return new self($code, $message);
    }

    public static function internal(): self
    {
        return new self(ErrorCode::INTERNAL, null);
    }

    /**
     * The canonical error envelope.
     *
     * @return array{error: array{code: string, message: string, status: int}}
     */
    public function payload(): array
    {
        return [
            'error' => [
                'code' => $this->code,
                'message' => $this->message,
                'status' => $this->status,
            ],
        ];
    }
}
