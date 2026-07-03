<?php

declare(strict_types=1);

namespace Introibo\Api\Edge;

use RuntimeException;

/**
 * Purges the Cloudflare edge cache via the API (#29). Configured from the environment
 * with the zone id and an API token; both are maintainer-provisioned secrets injected
 * at deploy, so this runs only in production (CI has no Cloudflare) and is exercised
 * behind the {@see EdgeCache} seam with a double.
 */
final class CloudflareEdge implements EdgeCache
{
    public function __construct(
        private readonly string $zoneId,
        private readonly string $token,
    ) {
    }

    /** Build from `INTROIBO_CF_ZONE` + `INTROIBO_CF_TOKEN`, or null when unset. */
    public static function fromEnvironment(): ?self
    {
        $zone = getenv('INTROIBO_CF_ZONE');
        $token = getenv('INTROIBO_CF_TOKEN');
        if (!is_string($zone) || $zone === '' || !is_string($token) || $token === '') {
            return null;
        }

        return new self($zone, $token);
    }

    public function purgeAll(): void
    {
        $url = sprintf('https://api.cloudflare.com/client/v4/zones/%s/purge_cache', $this->zoneId);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Authorization: Bearer ' . $this->token,
                    'Content-Type: application/json',
                ],
                'content' => '{"purge_everything":true}',
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            throw new RuntimeException('The Cloudflare purge request could not be sent.');
        }
    }
}
