<?php

declare(strict_types=1);

namespace Introibo\Api\Engine;

use DateTimeImmutable;
use DateTimeZone;
use Introibo\Api\Contract\ApiException;
use Introibo\Api\Contract\ErrorCode;
use Introibo\Api\Query\CalendarQuery;
use Introibo\Core\Overlay\CalendarCatalog;
use Throwable;

use function Introibo\Core\contract;

/**
 * The single boundary between the API and the Core engine (#6). Every piece of
 * liturgical truth the service returns comes through here — no handler, parser, or
 * cache ever calls Core directly or reimplements a rule. It also owns the small set
 * of API-facing capability lists (systems, calendars, languages) and the supported
 * date range, all derived from what Core actually offers so discovery stays honest.
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

    private ?string $dataVersion = null;

    /**
     * The resolved liturgical day for a query, as the frozen Core output contract
     * (#52). A validated query cannot make Core throw, so any Throwable here is an
     * internal fault surfaced as an opaque 500 — never leaked to the client.
     *
     * @return array<string, mixed>
     */
    public function day(CalendarQuery $query): array
    {
        try {
            return contract($query->date, false, $query->calendar);
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
        return $this->catalog()->descriptor($calendar)?->toArray();
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
            $reference = contract(new DateTimeImmutable('2000-01-01', new DateTimeZone('UTC')));
            $this->dataVersion = sprintf(
                'c%s+e%s+d%s',
                (string) ($reference['contractVersion'] ?? '0'),
                (string) ($reference['engineVersion'] ?? '0'),
                (string) ($reference['corpusVersion'] ?? '0'),
            );
        }

        return $this->dataVersion;
    }

    private function catalog(): CalendarCatalog
    {
        return $this->catalog ??= new CalendarCatalog();
    }
}
