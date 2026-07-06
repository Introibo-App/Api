<?php

declare(strict_types=1);

namespace Directorium\Api\Cache;

use RuntimeException;

/**
 * A filesystem store of pre-rendered response bodies for the hot calendar paths
 * (#13, #15). Each entry is one JSON file under a root, keyed by the request's
 * canonical path (`v1/day/2026-09-03/1962/sspx`). The whole tree is **namespaced by
 * the data version**, so a corpus/engine/contract bump lands in a fresh directory
 * and an old file can never be served stale — the rebuild-and-purge action (#28)
 * simply drops the previous version's directory.
 *
 * When no root is configured the store is disabled: reads miss and writes no-op, so
 * the service runs identically without any static tier (the default in dev/tests).
 */
final class StaticStore
{
    /** The version-namespaced base directory, or null when the store is disabled. */
    private ?string $base;

    public function __construct(?string $root, string $version)
    {
        $this->base = $root === null || $root === ''
            ? null
            : rtrim($root, '/\\') . '/' . self::sanitise($version);
    }

    /**
     * Build the store from the environment: `DIRECTORIUM_STATIC_ROOT` names the root, or
     * the store is disabled when it is unset. The version namespaces the tree.
     */
    public static function fromEnvironment(string $version): self
    {
        $root = getenv('DIRECTORIUM_STATIC_ROOT');

        return new self($root === false ? null : $root, $version);
    }

    public function enabled(): bool
    {
        return $this->base !== null;
    }

    /** The pre-rendered body for a key, or null on a miss (or when disabled). */
    public function get(string $key): ?string
    {
        if ($this->base === null) {
            return null;
        }

        $contents = @file_get_contents($this->pathFor($this->base, $key));

        return $contents === false ? null : $contents;
    }

    /** Write a body for a key, creating the directory tree. A no-op when disabled. */
    public function put(string $key, string $body): void
    {
        if ($this->base === null) {
            return;
        }

        $path = $this->pathFor($this->base, $key);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create static cache directory: %s', $directory));
        }

        file_put_contents($path, $body);
    }

    private function pathFor(string $base, string $key): string
    {
        return $base . '/' . trim($key, '/') . '.json';
    }

    private static function sanitise(string $version): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '-', $version) ?? 'unknown';
    }
}
