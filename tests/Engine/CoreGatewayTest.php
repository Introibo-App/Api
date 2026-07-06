<?php

declare(strict_types=1);

namespace Directorium\Api\Tests\Engine;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Api\Engine\CoreGateway;
use Directorium\Api\Query\CalendarQuery;
use Directorium\Api\Query\RangeQuery;
use PHPUnit\Framework\TestCase;

final class CoreGatewayTest extends TestCase
{
    public function testResolvesAKnownFeastThroughCore(): void
    {
        $day = $this->gateway()->day($this->query('1962-12-25', null));
        $celebration = $day['celebration'][0];

        self::assertSame('roman:temporale:christmas:nativity', $celebration['id']);
        self::assertSame(1, $celebration['rankOrdinal']);
        self::assertSame('white', $celebration['colour']['base']);
    }

    public function testTheSspxOverlayElevatesTheRank(): void
    {
        $universal = $this->gateway()->day($this->query('2026-09-03', null));
        $sspx = $this->gateway()->day($this->query('2026-09-03', 'sspx'));

        self::assertSame('roman:sanctorale:pius-x', $universal['celebration'][0]['id']);
        self::assertSame(3, $universal['celebration'][0]['rankOrdinal']);
        self::assertSame('roman:sanctorale:pius-x', $sspx['celebration'][0]['id']);
        self::assertSame(1, $sspx['celebration'][0]['rankOrdinal']);
    }

    public function testResolvesAMonthAsAnOrderedListOfDays(): void
    {
        $days = $this->gateway()->days(new RangeQuery(2026, 9, 'month', '2026-09', '1962', null, 'la'));

        self::assertCount(30, $days);
        self::assertSame('2026-09-01', $days[0]['date']);
        self::assertSame('2026-09-30', $days[29]['date']);
    }

    public function testResolvesAWholeYearIncludingLeapYears(): void
    {
        $common = $this->gateway()->days(new RangeQuery(2025, null, 'year', '2025', '1962', null, 'la'));
        $leap = $this->gateway()->days(new RangeQuery(2024, null, 'year', '2024', '1962', null, 'la'));

        self::assertCount(365, $common);
        self::assertSame('2025-01-01', $common[0]['date']);
        self::assertSame('2025-12-31', $common[364]['date']);
        self::assertCount(366, $leap);
    }

    public function testAMonthUnderTheOverlayCarriesTheElevation(): void
    {
        $days = $this->gateway()->days(new RangeQuery(2026, 9, 'month', '2026-09', '1962', 'sspx', 'la'));

        self::assertSame('2026-09-03', $days[2]['date']);
        self::assertSame(1, $days[2]['celebration'][0]['rankOrdinal']);
    }

    public function testSystemsDetailIsDiscoveryReady(): void
    {
        self::assertSame([['id' => '1962', 'edition' => 'roman:rubricae-1960']], $this->gateway()->systemsDetail());
    }

    public function testCalendarsDetailListsUniversalFirstThenParticulars(): void
    {
        $calendars = $this->gateway()->calendarsDetail();

        self::assertSame('universal', $calendars[0]['id']);
        self::assertNull($calendars[0]['particular']);
        self::assertContains('sspx', array_column($calendars, 'id'));
    }

    public function testCapabilityListsAreDerivedFromCore(): void
    {
        $gateway = $this->gateway();

        self::assertSame(['1962'], $gateway->systems());
        self::assertSame('roman:rubricae-1960', $gateway->editionFor('1962'));
        self::assertSame(['la'], $gateway->languages());
        self::assertContains('universal', $gateway->calendars());
        self::assertContains('sspx', $gateway->calendars());
    }

    public function testSupportChecks(): void
    {
        $gateway = $this->gateway();

        self::assertTrue($gateway->supportsSystem('1962'));
        self::assertFalse($gateway->supportsSystem('1969'));
        self::assertTrue($gateway->supportsCalendar('universal'));
        self::assertTrue($gateway->supportsCalendar('sspx'));
        self::assertFalse($gateway->supportsCalendar('bogus'));
        self::assertTrue($gateway->supportsLanguage('la'));
        self::assertFalse($gateway->supportsLanguage('tlh'));
    }

    public function testCalendarDescriptorReflectsTheOverlay(): void
    {
        $gateway = $this->gateway();

        self::assertNull($gateway->calendarDescriptor(null));

        $descriptor = $gateway->calendarDescriptor('sspx');
        self::assertNotNull($descriptor);
        self::assertSame('directorium:overlay:roman:sspx', $descriptor['id']);
        self::assertSame('Society of Saint Pius X', $descriptor['name']);
    }

    public function testDataVersionStampMovesWithEveryAxis(): void
    {
        // The stamp names all three axes that can change output.
        self::assertMatchesRegularExpression('/^c.+\+e.+\+d.+$/', $this->gateway()->dataVersion());
    }

    private function gateway(): CoreGateway
    {
        return new CoreGateway();
    }

    private function query(string $date, ?string $calendar): CalendarQuery
    {
        return new CalendarQuery(
            new DateTimeImmutable($date, new DateTimeZone('UTC')),
            $date,
            '1962',
            $calendar,
            'la',
        );
    }
}
