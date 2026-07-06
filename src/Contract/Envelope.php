<?php

declare(strict_types=1);

namespace Directorium\Api\Contract;

/**
 * The canonical success envelope every endpoint returns: a `data` payload and a
 * `meta` block. `meta` always carries the service-wide `dataVersion` stamp and,
 * where a request has parameters worth echoing, the normalised `request` the
 * service actually resolved. The shape is deliberately minimal and additive-only,
 * so a new `meta` field never breaks an existing client.
 */
final class Envelope
{
    /**
     * @param array<string, mixed>|list<mixed> $data
     * @param array<string, mixed> $meta
     * @return array{data: array<string, mixed>|list<mixed>, meta: array<string, mixed>}
     */
    public static function of(array $data, array $meta): array
    {
        return ['data' => $data, 'meta' => $meta];
    }

    /**
     * The `meta` block: the data-version stamp and, when non-empty, the echoed
     * request parameters the service normalised the response to.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public static function meta(string $dataVersion, array $request = []): array
    {
        $meta = ['dataVersion' => $dataVersion];
        if ($request !== []) {
            $meta['request'] = $request;
        }

        return $meta;
    }
}
