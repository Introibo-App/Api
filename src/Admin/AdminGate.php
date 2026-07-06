<?php

declare(strict_types=1);

namespace Directorium\Api\Admin;

use Directorium\Api\Contract\ApiException;
use Directorium\Api\Contract\ErrorCode;
use Directorium\Api\Http\Request;

/**
 * Guards the admin actions (rebuild, purge) with a single shared secret, separate
 * from tenant API keys. When no admin token is configured the gate is **disabled**
 * and the kernel does not even register the admin routes — so an unconfigured
 * service exposes no admin surface at all.
 */
final class AdminGate
{
    public function __construct(private readonly ?string $token = null)
    {
    }

    /** Build from `INTROIBO_ADMIN_TOKEN`, or disabled when unset. */
    public static function fromEnvironment(): self
    {
        $token = getenv('INTROIBO_ADMIN_TOKEN');

        return new self($token === false || $token === '' ? null : $token);
    }

    public function enabled(): bool
    {
        return $this->token !== null;
    }

    /**
     * Authorise an admin request, or throw 401. Compared in constant time via the
     * `Authorization: Bearer <token>` or `X-Admin-Token` header.
     */
    public function authorise(Request $request): void
    {
        $token = $this->token;
        $presented = $this->presented($request);
        if ($token === null || $presented === null || !hash_equals($token, $presented)) {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED, 'A valid admin token is required.');
        }
    }

    private function presented(Request $request): ?string
    {
        $authorization = $request->header('authorization');
        if ($authorization !== null && stripos($authorization, 'bearer ') === 0) {
            return trim(substr($authorization, 7));
        }

        return $request->header('x-admin-token');
    }
}
