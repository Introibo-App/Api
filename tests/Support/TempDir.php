<?php

declare(strict_types=1);

namespace Introibo\Api\Tests\Support;

/**
 * A unique temporary directory for tests that touch the static-cache filesystem,
 * with a recursive teardown so nothing is left behind.
 */
trait TempDir
{
    private function makeTempDir(): string
    {
        return sys_get_temp_dir() . '/introibo-api-' . bin2hex(random_bytes(6));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
