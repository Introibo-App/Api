<?php

declare(strict_types=1);

namespace Introibo\Api\Query;

use DateTimeImmutable;
use DateTimeZone;
use Introibo\Api\Contract\ApiException;
use Introibo\Api\Contract\ErrorCode;
use Introibo\Api\Engine\CoreGateway;
use Introibo\Api\Http\Request;

/**
 * Turns a raw request into a validated {@see CalendarQuery}, or throws the canonical
 * {@see ApiException} with a specific message. This is the whole of the service's
 * input handling (#7): it checks the date is a real calendar date in the supported
 * range and that the system, calendar, and language are ones Core actually offers,
 * before any Core resolution runs.
 */
final class QueryParser
{
    public function __construct(private readonly CoreGateway $core)
    {
    }

    public function parseDay(string $rawDate, Request $request): CalendarQuery
    {
        return new CalendarQuery(
            $this->parseDate($rawDate),
            $rawDate,
            $this->parseSystem($request),
            $this->parseCalendar($request),
            $this->parseLanguage($request),
        );
    }

    private function parseDate(string $raw): DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) !== 1) {
            throw ApiException::of(
                ErrorCode::MALFORMED_DATE,
                sprintf('"%s" is not an ISO calendar date in the form YYYY-MM-DD.', $raw),
            );
        }

        [$year, $month, $day] = array_map('intval', explode('-', $raw));
        if (!checkdate($month, $day, $year)) {
            throw ApiException::of(ErrorCode::MALFORMED_DATE, sprintf('"%s" is not a real calendar date.', $raw));
        }

        if ($year < CoreGateway::MIN_YEAR || $year > CoreGateway::MAX_YEAR) {
            throw ApiException::of(
                ErrorCode::DATE_OUT_OF_RANGE,
                sprintf(
                    'The year %d is outside the supported range %d–%d.',
                    $year,
                    CoreGateway::MIN_YEAR,
                    CoreGateway::MAX_YEAR,
                ),
            );
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new DateTimeZone('UTC'));
        if ($date === false) {
            // Unreachable after the checks above; guards the return type.
            throw ApiException::of(ErrorCode::MALFORMED_DATE, sprintf('"%s" could not be parsed.', $raw));
        }

        return $date;
    }

    private function parseSystem(Request $request): string
    {
        $system = $request->query('system', CoreGateway::DEFAULT_SYSTEM) ?? CoreGateway::DEFAULT_SYSTEM;
        if (!$this->core->supportsSystem($system)) {
            throw ApiException::of(
                ErrorCode::UNSUPPORTED_SYSTEM,
                sprintf(
                    'The rubric system "%s" is not supported. Supported: %s.',
                    $system,
                    implode(', ', $this->core->systems()),
                ),
            );
        }

        return $system;
    }

    private function parseCalendar(Request $request): ?string
    {
        $calendar = $request->query('calendar', CoreGateway::UNIVERSAL) ?? CoreGateway::UNIVERSAL;
        if (!$this->core->supportsCalendar($calendar)) {
            throw ApiException::of(
                ErrorCode::UNKNOWN_CALENDAR,
                sprintf(
                    'The calendar "%s" is not known. Known: %s.',
                    $calendar,
                    implode(', ', $this->core->calendars()),
                ),
            );
        }

        return $calendar === CoreGateway::UNIVERSAL ? null : $calendar;
    }

    private function parseLanguage(Request $request): string
    {
        $language = $request->query('lang', CoreGateway::DEFAULT_LANGUAGE) ?? CoreGateway::DEFAULT_LANGUAGE;
        if (!$this->core->supportsLanguage($language)) {
            throw ApiException::of(
                ErrorCode::UNSUPPORTED_LANGUAGE,
                sprintf(
                    'The language "%s" is not supported. Supported: %s.',
                    $language,
                    implode(', ', $this->core->languages()),
                ),
            );
        }

        return $language;
    }
}
