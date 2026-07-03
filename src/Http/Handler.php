<?php

declare(strict_types=1);

namespace Introibo\Api\Http;

/**
 * A route handler: turns a matched request (plus any path parameters the router
 * captured) into a response. Handlers hold no calendar logic of their own — they
 * validate input and call the Core gateway — so the whole HTTP surface stays thin.
 */
interface Handler
{
    /**
     * @param array<string, string> $params Path parameters captured by the router.
     */
    public function handle(Request $request, array $params): Response;
}
