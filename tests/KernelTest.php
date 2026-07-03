<?php

declare(strict_types=1);

namespace Introibo\Api\Tests;

use Introibo\Api\Cache\StaticStore;
use Introibo\Api\Engine\CoreGateway;
use Introibo\Api\Http\Request;
use Introibo\Api\Http\Response;
use Introibo\Api\Kernel;
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
