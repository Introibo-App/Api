<?php

declare(strict_types=1);

namespace Introibo\Api\Tests;

use Introibo\Api\Http\Request;
use Introibo\Api\Http\Response;
use Introibo\Api\Kernel;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end coverage of the whole request → response path: a real kernel over the
 * vendored Core engine, driven by hand-built requests. No network, no SAPI.
 */
final class KernelTest extends TestCase
{
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

    public function testMalformedDateReturns400(): void
    {
        $this->assertError($this->get('/v1/day/not-a-date'), 400, 'malformed_date');
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

    public function testHealthEndpointIsLive(): void
    {
        $response = $this->get('/v1/health');

        self::assertSame(200, $response->status);
        $body = $this->decode($response);
        self::assertSame('ok', $body['data']['status']);
        self::assertArrayHasKey('dataVersion', $body['meta']);
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
