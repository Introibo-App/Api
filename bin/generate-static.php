<?php

/**
 * Build-time static generation for the hot calendar paths (#14).
 *
 * Warms the {@see StaticStore} for a range of civil years by replaying every hot
 * request — year, each month, each day, for every supported system and calendar —
 * through the real {@see Kernel}. Because the kernel caches read-through, each request
 * computes once and writes its body to the static tier, so this reuses the exact
 * response shape the live endpoints serve (no second serialiser to drift). The civil
 * year is resolved once per (year, calendar) and every day is read off it, so a whole
 * year is one Core resolution.
 *
 * Usage:
 *   DIRECTORIUM_STATIC_ROOT=/path php bin/generate-static.php START_YEAR END_YEAR
 *   php bin/generate-static.php START_YEAR END_YEAR /path/to/static-root
 *
 * The tree is namespaced by the data version; point the web server / edge at the
 * matching version directory. Re-run after a data-version bump, then purge the old
 * version's directory (#28).
 */

declare(strict_types=1);

use Directorium\Api\Cache\StaticStore;
use Directorium\Api\Engine\CoreGateway;
use Directorium\Api\Http\Request;
use Directorium\Api\Kernel;

require dirname(__DIR__) . '/vendor/autoload.php';

$startYear = isset($argv[1]) ? (int) $argv[1] : 0;
$endYear = isset($argv[2]) ? (int) $argv[2] : 0;
$root = $argv[3] ?? (getenv('DIRECTORIUM_STATIC_ROOT') ?: null);

if ($startYear < CoreGateway::MIN_YEAR || $endYear > CoreGateway::MAX_YEAR || $startYear > $endYear) {
    fwrite(STDERR, sprintf(
        "usage: php bin/generate-static.php START_YEAR END_YEAR [ROOT]\n" .
        "  years must be within %d-%d and ascending; ROOT via arg or DIRECTORIUM_STATIC_ROOT\n",
        CoreGateway::MIN_YEAR,
        CoreGateway::MAX_YEAR,
    ));
    exit(2);
}

if (!is_string($root) || $root === '') {
    fwrite(STDERR, "error: no static root — pass it as the 3rd argument or set DIRECTORIUM_STATIC_ROOT\n");
    exit(2);
}

$core = new CoreGateway();
$store = new StaticStore($root, $core->dataVersion());
$kernel = new Kernel($core, $store);
$timezone = new DateTimeZone('UTC');

/**
 * Warm one path, failing loudly if the service would not serve it.
 *
 * @param array<string, string> $query
 */
$warm = static function (string $path, array $query) use ($kernel): void {
    $response = $kernel->handle(new Request('GET', $path, $query));
    if ($response->status !== 200) {
        throw new RuntimeException(sprintf(
            'Generation failed for %s: HTTP %d %s',
            $path,
            $response->status,
            $response->body,
        ));
    }
};

$written = 0;
foreach ($core->systems() as $system) {
    foreach ($core->calendars() as $calendar) {
        $query = ['system' => $system, 'calendar' => $calendar];
        for ($year = $startYear; $year <= $endYear; $year++) {
            $warm('/v1/year/' . $year, $query);
            $written++;
            for ($month = 1; $month <= 12; $month++) {
                $warm(sprintf('/v1/month/%04d-%02d', $year, $month), $query);
                $written++;
            }

            $start = new DateTimeImmutable(sprintf('%04d-01-01', $year), $timezone);
            $end = $start->modify('+1 year');
            for ($date = $start; $date < $end; $date = $date->modify('+1 day')) {
                $warm('/v1/day/' . $date->format('Y-m-d'), $query);
                $written++;
            }
            fwrite(STDOUT, sprintf("warmed %d [%s/%s]\n", $year, $system, $calendar));
        }
    }
}

fwrite(STDOUT, sprintf("Wrote %d static files for data version %s.\n", $written, $core->dataVersion()));
