<?php

declare(strict_types=1);

namespace Directorium\Api\Engine;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Api\Contract\ApiException;
use Directorium\Api\Contract\ErrorCode;
use Directorium\Api\Query\CalendarQuery;
use Directorium\Api\Query\RangeQuery;
use Directorium\Core\Contract\CalendarDescriptor;
use Directorium\Core\Contract\DayContract;
use Directorium\Core\Overlay\CalendarCatalog;
use Directorium\Core\Precedence\ResolvedYear;
use Throwable;

/**
 * The single boundary between the API and the Core engine (#6). Every piece of
 * liturgical truth the service returns comes through here — no handler, parser, or
 * cache ever calls Core directly or reimplements a rule. It resolves a civil year
 * once via {@see CalendarCatalog} and serialises days off that index, so a month or
 * a whole year costs one resolution. It also owns the small set of API-facing
 * capability lists (systems, calendars, languages) and the supported date range,
 * all derived from what Core actually offers so discovery stays honest.
 */
final class CoreGateway
{
    /** API-facing rubric-system label => the Core edition URN it resolves under. */
    private const SYSTEMS = ['1962' => 'roman:rubricae-1960'];

    /** Languages the corpus carries. Latin is guaranteed; vernacular is progressive. */
    private const LANGUAGES = ['la'];

    public const DEFAULT_SYSTEM = '1962';
    public const DEFAULT_LANGUAGE = 'la';

    /** The selector for the universal 1962 calendar (Core's null). */
    public const UNIVERSAL = 'universal';

    /**
     * The civil years the service resolves — the proven range of Core's golden
     * fixture (#365). Outside it, resolution is unverified, so the service declines.
     */
    public const MIN_YEAR = 1583;
    public const MAX_YEAR = 2200;

    private ?CalendarCatalog $catalog = null;

    /** @var array<string, ResolvedYear> Resolved civil years, keyed by "year|calendar". */
    private array $resolvedYears = [];

    /** @var array<string, CalendarDescriptor|null> Descriptors, keyed by calendar. */
    private array $descriptors = [];

    private ?string $dataVersion = null;

    /**
     * The resolved liturgical day for a query, as the frozen Core output contract
     * (#52).
     *
     * @return array<string, mixed>
     */
    public function day(CalendarQuery $query): array
    {
        try {
            return $this->serialise((int) $query->date->format('Y'), $query->calendar, $query->date);
        } catch (Throwable $e) {
            throw ApiException::of(ErrorCode::INTERNAL, null, $e);
        }
    }

    /**
     * Every resolved day in a month or a whole year, ascending by date — each the
     * frozen Core output contract. The civil year is resolved once and every day is
     * read off it, so the range costs a single resolution.
     *
     * @return list<array<string, mixed>>
     */
    public function days(RangeQuery $query): array
    {
        try {
            $resolved = $this->resolvedYear($query->year, $query->calendar);
            $descriptor = $this->descriptor($query->calendar);

            $days = [];
            foreach ($this->datesIn($query) as $date) {
                $days[] = DayContract::from($resolved->day($date), $resolved->provenance(), $descriptor)->toArray();
            }

            return $days;
        } catch (Throwable $e) {
            throw ApiException::of(ErrorCode::INTERNAL, null, $e);
        }
    }

    /**
     * The rubric systems the service supports, by stable label. One today (`1962`);
     * 1954/1955 join as Core gains them.
     *
     * @return list<string>
     */
    public function systems(): array
    {
        // Numeric-string keys ('1962') become ints in PHP arrays; restore the label.
        return array_map(static fn (int|string $label): string => (string) $label, array_keys(self::SYSTEMS));
    }

    /** The Core edition URN a supported system resolves under. */
    public function editionFor(string $system): string
    {
        return self::SYSTEMS[$system];
    }

    /**
     * The systems as discovery records — the label and the Core edition it resolves
     * under.
     *
     * @return list<array{id: string, edition: string}>
     */
    public function systemsDetail(): array
    {
        return array_map(fn (string $system): array => [
            'id' => $system,
            'edition' => $this->editionFor($system),
        ], $this->systems());
    }

    /**
     * The calendars the service supports: the universal 1962 base plus every
     * particular calendar Core ships as an overlay.
     *
     * @return list<string>
     */
    public function calendars(): array
    {
        return array_merge([self::UNIVERSAL], $this->catalog()->particularCalendars());
    }

    /**
     * The calendars as discovery records: the universal base (no particular block)
     * plus each particular calendar with its contract descriptor.
     *
     * @return list<array{id: string, name: string, particular: array{id: string, name: string}|null}>
     */
    public function calendarsDetail(): array
    {
        $calendars = [[
            'id' => self::UNIVERSAL,
            'name' => 'Universal 1962 calendar',
            'particular' => null,
        ]];

        foreach ($this->catalog()->particularCalendars() as $slug) {
            $descriptor = $this->calendarDescriptor($slug);
            $calendars[] = [
                'id' => $slug,
                'name' => $descriptor['name'] ?? $slug,
                'particular' => $descriptor,
            ];
        }

        return $calendars;
    }

    /**
     * @return list<string>
     */
    public function languages(): array
    {
        return self::LANGUAGES;
    }

    public function supportsSystem(string $system): bool
    {
        return isset(self::SYSTEMS[$system]);
    }

    public function supportsCalendar(string $calendar): bool
    {
        return in_array($calendar, $this->calendars(), true);
    }

    public function supportsLanguage(string $language): bool
    {
        return in_array($language, self::LANGUAGES, true);
    }

    /**
     * The descriptor `{id, name}` for a particular calendar, or null for the
     * universal base — used by the discovery endpoint.
     *
     * @return array{id: string, name: string}|null
     */
    public function calendarDescriptor(?string $calendar): ?array
    {
        return $this->descriptor($calendar)?->toArray();
    }

    /**
     * The service-wide data-version stamp: a compact, opaque token that moves when
     * any axis that can change output moves — the contract shape, the engine, or the
     * corpus. A client can key a cache on it; the caching layer (#17) uses it for
     * ETags and static-file versioning. Computed once from a reference resolution.
     */
    public function dataVersion(): string
    {
        if ($this->dataVersion === null) {
            $reference = $this->serialise(2000, null, new DateTimeImmutable('2000-01-01', new DateTimeZone('UTC')));
            $this->dataVersion = sprintf(
                'c%s+e%s+d%s',
                (string) ($reference['contractVersion'] ?? '0'),
                (string) ($reference['engineVersion'] ?? '0'),
                (string) ($reference['corpusVersion'] ?? '0'),
            );
        }

        return $this->dataVersion;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialise(int $year, ?string $calendar, DateTimeImmutable $date): array
    {
        $resolved = $this->resolvedYear($year, $calendar);

        return DayContract::from(
            $resolved->day($date),
            $resolved->provenance(),
            $this->descriptor($calendar),
        )->toArray();
    }

    /**
     * The dates the range spans, ascending — a whole civil year or one month.
     *
     * @return list<DateTimeImmutable>
     */
    private function datesIn(RangeQuery $query): array
    {
        $timezone = new DateTimeZone('UTC');
        $start = $query->month === null
            ? new DateTimeImmutable(sprintf('%04d-01-01', $query->year), $timezone)
            : new DateTimeImmutable(sprintf('%04d-%02d-01', $query->year, $query->month), $timezone);
        $end = $start->modify($query->month === null ? '+1 year' : '+1 month');

        $dates = [];
        for ($date = $start; $date < $end; $date = $date->modify('+1 day')) {
            $dates[] = $date;
        }

        return $dates;
    }

    private function resolvedYear(int $year, ?string $calendar): ResolvedYear
    {
        $key = $year . '|' . ($calendar ?? '');

        return $this->resolvedYears[$key] ??= $this->catalog()->resolver($calendar)->resolveYear($year);
    }

    private function descriptor(?string $calendar): ?CalendarDescriptor
    {
        $key = $calendar ?? '';
        if (!array_key_exists($key, $this->descriptors)) {
            $this->descriptors[$key] = $this->catalog()->descriptor($calendar);
        }

        return $this->descriptors[$key];
    }

    private function catalog(): CalendarCatalog
    {
        return $this->catalog ??= new CalendarCatalog();
    }
}
