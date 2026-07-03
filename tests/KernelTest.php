<?php

declare(strict_types=1);

namespace Introibo\Api\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Introibo\Api\Admin\AdminGate;
use Introibo\Api\Auth\AccessControl;
use Introibo\Api\Auth\InMemoryKeyStore;
use Introibo\Api\Auth\KeyIssuer;
use Introibo\Api\Auth\Tenant;
use Introibo\Api\Cache\StaticStore;
use Introibo\Api\Engine\CoreGateway;
use Introibo\Api\Http\Request;
use Introibo\Api\Http\Response;
use Introibo\Api\Kernel;
use Introibo\Api\Tests\Support\FixedClock;
use Introibo\Api\Tests\Support\RecordingEdge;
use Introibo\Api\Tests\Support\TempDir;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end coverage of the whole request → response path: a real kernel over the
 * vendored Core engine, driven by hand-built requests. No network, no SAPI.
 */
final class KernelTest extends TestCase
{
    use TempDir;

    public function testDayEndpointReturnsTheResolvedDayInTheEnvelope(): void
    {
        $response = $this->get('/v1/day/1962-12-25');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('application/json', $response->headers['content-type']);

        $body = $this->decode($response);
        self::assertSame('roman:temporale:christmas:nativity', $body['data']['celebration'][0]['id']);
        self::assertArrayHasKey('dataVersion', $body['meta']);
        self::assertSame('1962-12-25', $body['meta']['request']['date']);
        self::assertSame('universal', $body['meta']['request']['calendar']);
    }

    public function testDayEndpointAppliesTheSelectedCalendar(): void
    {
        $response = $this->get('/v1/day/2026-09-03', ['calendar' => 'sspx']);

        $body = $this->decode($response);
        self::assertSame(1, $body['data']['celebration'][0]['rankOrdinal']);
        self::assertSame('sspx', $body['meta']['request']['calendar']);
    }

    public function testMonthEndpointReturnsAnOrderedList(): void
    {
        $response = $this->get('/v1/month/2026-09');

        self::assertSame(200, $response->status);
        $body = $this->decode($response);
        self::assertCount(30, $body['data']);
        self::assertSame('2026-09-01', $body['data'][0]['date']);
        self::assertSame(30, $body['meta']['count']);
        self::assertSame('2026-09', $body['meta']['request']['month']);
    }

    public function testYearEndpointResolvesEveryDay(): void
    {
        $response = $this->get('/v1/year/2025');

        $body = $this->decode($response);
        self::assertCount(365, $body['data']);
        self::assertSame('2025', $body['meta']['request']['year']);
    }

    public function testMetaEndpointAdvertisesCapabilities(): void
    {
        $response = $this->get('/v1/meta');

        self::assertSame(200, $response->status);
        $body = $this->decode($response);
        self::assertSame([['id' => '1962', 'edition' => 'roman:rubricae-1960']], $body['data']['systems']);
        self::assertSame(['la'], $body['data']['languages']);
        self::assertSame(1583, $body['data']['range']['minYear']);
        self::assertSame(2200, $body['data']['range']['maxYear']);
        self::assertSame('universal', $body['data']['calendars'][0]['id']);
        self::assertSame('sspx', $body['data']['calendars'][1]['id']);
        self::assertSame('introibo:overlay:roman:sspx', $body['data']['calendars'][1]['particular']['id']);
    }

    public function testMalformedDateReturns400(): void
    {
        $this->assertError($this->get('/v1/day/not-a-date'), 400, 'malformed_date');
    }

    public function testMalformedMonthReturns400(): void
    {
        $this->assertError($this->get('/v1/month/2026-13'), 400, 'malformed_date');
    }

    public function testOutOfRangeDateReturns422(): void
    {
        $this->assertError($this->get('/v1/day/1500-01-01'), 422, 'date_out_of_range');
    }

    public function testUnknownCalendarReturns422(): void
    {
        $this->assertError($this->get('/v1/day/2026-09-03', ['calendar' => 'bogus']), 422, 'unknown_calendar');
    }

    public function testUnknownRouteReturns404(): void
    {
        $this->assertError($this->get('/v1/nope'), 404, 'not_found');
    }

    public function testWrongMethodReturns405(): void
    {
        $response = (new Kernel())->handle(new Request('POST', '/v1/day/2026-09-03'));

        $this->assertError($response, 405, 'method_not_allowed');
    }

    public function testCalendarResponsesAreWrittenThroughTheStaticStore(): void
    {
        $root = $this->makeTempDir();
        try {
            $core = new CoreGateway();
            $store = new StaticStore($root, $core->dataVersion());
            $kernel = new Kernel($core, $store);

            $first = $kernel->handle(new Request('GET', '/v1/day/2026-09-03', ['calendar' => 'sspx']));
            $second = $kernel->handle(new Request('GET', '/v1/day/2026-09-03', ['calendar' => 'sspx']));

            self::assertSame('MISS', $first->headers['x-cache']);
            self::assertSame('HIT', $second->headers['x-cache']);
            self::assertSame($first->body, $second->body);
            self::assertArrayHasKey('cache-control', $first->headers);
            self::assertNotNull(
                $store->get('v1/day/2026-09-03/1962/sspx'),
                'The day should be written to the static tier.',
            );
        } finally {
            $this->removeDir($root);
        }
    }

    public function testHealthEndpointIsLive(): void
    {
        $response = $this->get('/v1/health');

        self::assertSame(200, $response->status);
        $body = $this->decode($response);
        self::assertSame('ok', $body['data']['status']);
        self::assertArrayHasKey('dataVersion', $body['meta']);
    }

    public function testEveryResponseCarriesTheDataVersionHeader(): void
    {
        self::assertArrayHasKey('x-data-version', $this->get('/v1/day/1962-12-25')->headers);
        self::assertArrayHasKey('x-data-version', $this->get('/v1/nope')->headers);
    }

    public function testCalendarResponsesCarryAnEtagAndImmutableCacheControl(): void
    {
        $response = $this->get('/v1/day/1962-12-25');

        self::assertArrayHasKey('etag', $response->headers);
        self::assertStringContainsString('immutable', $response->headers['cache-control']);
    }

    public function testConditionalGetReturns304WithoutABody(): void
    {
        $first = $this->get('/v1/day/1962-12-25');
        $etag = $first->headers['etag'];

        $second = (new Kernel())->handle(
            new Request('GET', '/v1/day/1962-12-25', [], ['if-none-match' => $etag]),
        );

        self::assertSame(304, $second->status);
        self::assertSame('', $second->body);
        self::assertSame($etag, $second->headers['etag']);
    }

    public function testHealthIsNotCacheable(): void
    {
        self::assertSame('no-store', $this->get('/v1/health')->headers['cache-control']);
    }

    public function testMetaIsCacheableWithAnEtag(): void
    {
        $response = $this->get('/v1/meta');

        self::assertArrayHasKey('etag', $response->headers);
        self::assertStringContainsString('immutable', $response->headers['cache-control']);
    }

    public function testMeteredEndpointRequiresAKeyWhenAccessControlIsEnabled(): void
    {
        $kernel = $this->guardedKernel(new InMemoryKeyStore());

        $response = $kernel->handle(new Request('GET', '/v1/day/1962-12-25'));

        self::assertSame(401, $response->status);
        self::assertSame('unauthenticated', $this->decode($response)['error']['code']);
        self::assertArrayHasKey('x-data-version', $response->headers);
    }

    public function testValidKeyPassesTheGateAndCarriesRateHeaders(): void
    {
        $store = new InMemoryKeyStore();
        $store->putTenant(new Tenant('t', 'Acme', 1000));
        $issued = (new KeyIssuer($store))->issue('t', 'main', 60);

        $response = $this->guardedKernel($store)->handle(
            new Request('GET', '/v1/day/1962-12-25', [], ['authorization' => 'Bearer ' . $issued->secret]),
        );

        self::assertSame(200, $response->status);
        self::assertSame('60', $response->headers['x-ratelimit-limit']);
        self::assertArrayHasKey('x-ratelimit-remaining', $response->headers);
    }

    public function testHealthAndMetaSkipTheGate(): void
    {
        $kernel = $this->guardedKernel(new InMemoryKeyStore());

        self::assertSame(200, $kernel->handle(new Request('GET', '/v1/health'))->status);
        self::assertSame(200, $kernel->handle(new Request('GET', '/v1/meta'))->status);
    }

    public function testRateLimit429CarriesRetryAfterAndTheDataVersion(): void
    {
        $store = new InMemoryKeyStore();
        $store->putTenant(new Tenant('t', 'Acme'));
        $issued = (new KeyIssuer($store))->issue('t', 'main', 1);
        $kernel = $this->guardedKernel($store);
        $request = new Request('GET', '/v1/day/1962-12-25', [], ['authorization' => 'Bearer ' . $issued->secret]);

        self::assertSame(200, $kernel->handle($request)->status);
        $second = $kernel->handle($request);

        self::assertSame(429, $second->status);
        self::assertSame('rate_limited', $this->decode($second)['error']['code']);
        self::assertArrayHasKey('retry-after', $second->headers);
        self::assertArrayHasKey('x-data-version', $second->headers);
    }

    public function testPolicyEndpointsArePublic(): void
    {
        $aup = $this->get('/v1/aup');
        self::assertSame(200, $aup->status);
        self::assertSame('aup', $this->decode($aup)['data']['slug']);
        self::assertNotSame('', $this->decode($aup)['data']['body']);

        self::assertSame('terms', $this->decode($this->get('/v1/terms'))['data']['slug']);
    }

    public function testAdminRoutesAreAbsentWithoutAToken(): void
    {
        $response = (new Kernel())->handle(new Request('POST', '/v1/admin/purge'));

        self::assertSame(404, $response->status);
    }

    public function testAdminPurgeRequiresTheTokenAndHitsTheEdge(): void
    {
        $edge = new RecordingEdge();
        $kernel = $this->adminKernel($edge);

        self::assertSame(401, $kernel->handle(new Request('POST', '/v1/admin/purge'))->status);
        self::assertSame(0, $edge->purges);

        $ok = $kernel->handle(new Request('POST', '/v1/admin/purge', [], ['x-admin-token' => 'admin-secret']));
        self::assertSame(200, $ok->status);
        self::assertTrue($this->decode($ok)['data']['purged']);
        self::assertSame(1, $edge->purges);
    }

    public function testAdminRebuildReportsTheVersionAndPurges(): void
    {
        $edge = new RecordingEdge();

        $response = $this->adminKernel($edge)->handle(
            new Request('POST', '/v1/admin/rebuild', [], ['x-admin-token' => 'admin-secret']),
        );

        self::assertSame(200, $response->status);
        $data = $this->decode($response)['data'];
        self::assertArrayHasKey('dataVersion', $data);
        self::assertTrue($data['purged']);
        self::assertSame(1, $edge->purges);
    }

    private function guardedKernel(InMemoryKeyStore $store): Kernel
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-07-03 01:27:30', new DateTimeZone('UTC')));

        return new Kernel(null, null, new AccessControl($store, $clock));
    }

    private function adminKernel(RecordingEdge $edge): Kernel
    {
        return new Kernel(null, null, null, new AdminGate('admin-secret'), $edge);
    }

    /**
     * @param array<string, string> $query
     */
    private function get(string $path, array $query = []): Response
    {
        return (new Kernel())->handle(new Request('GET', $path, $query));
    }

    private function assertError(Response $response, int $status, string $code): void
    {
        self::assertSame($status, $response->status);
        $body = $this->decode($response);
        self::assertSame($code, $body['error']['code']);
        self::assertSame($status, $body['error']['status']);
    }

    /**
     * @return array<mixed>
     */
    private function decode(Response $response): array
    {
        $data = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
