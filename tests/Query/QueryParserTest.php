<?php

declare(strict_types=1);

namespace Introibo\Api\Tests\Query;

use Introibo\Api\Contract\ApiException;
use Introibo\Api\Engine\CoreGateway;
use Introibo\Api\Http\Request;
use Introibo\Api\Query\QueryParser;
use PHPUnit\Framework\TestCase;

final class QueryParserTest extends TestCase
{
    public function testParsesAValidDayWithDefaults(): void
    {
        $query = $this->parser()->parseDay('2026-09-15', $this->request());

        self::assertSame('2026-09-15', $query->dateString);
        self::assertSame('2026-09-15', $query->date->format('Y-m-d'));
        self::assertSame('1962', $query->system);
        self::assertNull($query->calendar);
        self::assertSame('universal', $query->calendarLabel());
        self::assertSame('la', $query->language);
    }

    public function testAppliesCalendarSystemAndLanguageOverrides(): void
    {
        $query = $this->parser()->parseDay('2026-09-03', $this->request([
            'calendar' => 'sspx',
            'system' => '1962',
            'lang' => 'la',
        ]));

        self::assertSame('sspx', $query->calendar);
        self::assertSame(
            ['date' => '2026-09-03', 'system' => '1962', 'calendar' => 'sspx', 'language' => 'la'],
            $query->parameters(),
        );
    }

    public function testRejectsAMalformedDate(): void
    {
        $this->assertRejects('15-09-2026', [], 'malformed_date', 400);
    }

    public function testRejectsAnImpossibleDate(): void
    {
        $this->assertRejects('2026-02-30', [], 'malformed_date', 400);
    }

    public function testRejectsAnOutOfRangeYear(): void
    {
        $this->assertRejects('1500-01-01', [], 'date_out_of_range', 422);
    }

    public function testRejectsAnUnsupportedSystem(): void
    {
        $this->assertRejects('2026-09-15', ['system' => '1969'], 'unsupported_system', 422);
    }

    public function testRejectsAnUnknownCalendar(): void
    {
        $this->assertRejects('2026-09-15', ['calendar' => 'bogus'], 'unknown_calendar', 422);
    }

    public function testRejectsAnUnsupportedLanguage(): void
    {
        $this->assertRejects('2026-09-15', ['lang' => 'tlh'], 'unsupported_language', 422);
    }

    public function testParsesAValidMonth(): void
    {
        $query = $this->parser()->parseMonth('2026-09', $this->request(['calendar' => 'sspx']));

        self::assertSame(2026, $query->year);
        self::assertSame(9, $query->month);
        self::assertSame('sspx', $query->calendar);
        self::assertSame(
            ['month' => '2026-09', 'system' => '1962', 'calendar' => 'sspx', 'language' => 'la'],
            $query->parameters(),
        );
    }

    public function testParsesAValidYear(): void
    {
        $query = $this->parser()->parseYear('2026', $this->request());

        self::assertSame(2026, $query->year);
        self::assertNull($query->month);
        self::assertSame(
            ['year' => '2026', 'system' => '1962', 'calendar' => 'universal', 'language' => 'la'],
            $query->parameters(),
        );
    }

    public function testRejectsAMalformedMonth(): void
    {
        try {
            $this->parser()->parseMonth('2026-9', $this->request());
            self::fail('Expected a malformed_date ApiException.');
        } catch (ApiException $e) {
            self::assertSame('malformed_date', $e->error()->code);
        }
    }

    public function testRejectsAnImpossibleMonth(): void
    {
        try {
            $this->parser()->parseMonth('2026-13', $this->request());
            self::fail('Expected a malformed_date ApiException.');
        } catch (ApiException $e) {
            self::assertSame('malformed_date', $e->error()->code);
        }
    }

    public function testRejectsAMalformedYear(): void
    {
        try {
            $this->parser()->parseYear('26', $this->request());
            self::fail('Expected a malformed_date ApiException.');
        } catch (ApiException $e) {
            self::assertSame('malformed_date', $e->error()->code);
        }
    }

    public function testRejectsAnOutOfRangeYearForARange(): void
    {
        try {
            $this->parser()->parseYear('3000', $this->request());
            self::fail('Expected a date_out_of_range ApiException.');
        } catch (ApiException $e) {
            self::assertSame('date_out_of_range', $e->error()->code);
        }
    }

    /**
     * @param array<string, string> $query
     */
    private function assertRejects(string $rawDate, array $query, string $code, int $status): void
    {
        try {
            $this->parser()->parseDay($rawDate, $this->request($query));
            self::fail(sprintf('Expected an ApiException with code %s.', $code));
        } catch (ApiException $e) {
            self::assertSame($code, $e->error()->code);
            self::assertSame($status, $e->error()->status);
        }
    }

    private function parser(): QueryParser
    {
        return new QueryParser(new CoreGateway());
    }

    /**
     * @param array<string, string> $query
     */
    private function request(array $query = []): Request
    {
        return new Request('GET', '/v1/day/x', $query);
    }
}
