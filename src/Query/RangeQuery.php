<?php

declare(strict_types=1);

namespace Introibo\Api\Query;

use Introibo\Api\Engine\CoreGateway;

/**
 * A validated, normalised request for a span of days — a whole civil year, or a
 * single month of one. Produced only by {@see QueryParser}. `month` is null for a
 * year request; `rangeKey`/`rangeValue` carry how the span was addressed so the
 * response `meta` echoes it back faithfully (`month: 2026-09` or `year: 2026`).
 */
final readonly class RangeQuery
{
    public function __construct(
        public int $year,
        public ?int $month,
        public string $rangeKey,
        public string $rangeValue,
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
        return sprintf('v1/%s/%s/%s/%s', $this->rangeKey, $this->rangeValue, $this->system, $this->calendarLabel());
    }

    /**
     * The request parameters echoed back in the response `meta`.
     *
     * @return array<string, string>
     */
    public function parameters(): array
    {
        return [
            $this->rangeKey => $this->rangeValue,
            'system' => $this->system,
            'calendar' => $this->calendarLabel(),
            'language' => $this->language,
        ];
    }
}
