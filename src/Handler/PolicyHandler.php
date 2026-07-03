<?php

declare(strict_types=1);

namespace Introibo\Api\Handler;

use Introibo\Api\Contract\Envelope;
use Introibo\Api\Http\Handler;
use Introibo\Api\Http\Request;
use Introibo\Api\Http\Response;
use Introibo\Api\Legal\Policy;

/**
 * `GET /v1/aup` and `GET /v1/terms` (#30) — the acceptable-use policy and terms of
 * use, served as data (slug, title, version, Markdown body). Public, and cacheable
 * by its own policy version.
 */
final class PolicyHandler implements Handler
{
    public function __construct(private readonly Policy $policy)
    {
    }

    public function handle(Request $request, array $params): Response
    {
        return Response::json(
            Envelope::of($this->policy->toArray(), ['policyVersion' => $this->policy->version]),
        )->withHeader('cache-control', 'public, max-age=86400');
    }
}
