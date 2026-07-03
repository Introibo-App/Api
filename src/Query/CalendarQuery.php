<?php

declare(strict_types=1);

namespace Introibo\Api\Query;

use DateTimeImmutable;
use Introibo\Api\Engine\CoreGateway;

/**
 * A validated, normalised calendar request: the civil date to resolve, the rubric
 * system, the calendar (null for the universal 1962 base, else a particular-calendar
 * slug), and the language. Produced only by {@see QueryParser}, so anything holding
 * one knows every field is already checked against what Core supports.
 */
final readonly class CalendarQuery
{
    public function __construct(
        public DateTimeImmutable $date,
        public string $dateString,
        public string $system,
        public ?string $calendar,
        public string $language,
    ) {
    }

    /** The calendar as a stable label — `universal` stands in for the null base. */
    public function calendarLabel(): string
    {
        return $this->calendar ?? CoreGateway::UNIVERSAL;
    }

    /** The canonical static-cache key: the request as one filesystem-safe path. */
    public function cacheKey(): string
    {
        return sprintf('v1/day/%s/%s/%s', $this->dateString, $this->system, $this->calendarLabel());
    }

    /**
     * The request parameters echoed back in the response `meta`, so a client sees
     * exactly which (date, system, calendar, language) the service resolved.
     *
     * @return array<string, string>
     */
    public function parameters(): array
    {
        return [
            'date' => $this->dateString,
            'system' => $this->system,
            'calendar' => $this->calendarLabel(),
            'language' => $this->language,
        ];
    }
}
