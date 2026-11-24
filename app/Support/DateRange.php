<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Builds and normalizes the date ranges used by dashboard and audit filters.
 */
final class DateRange
{
    /**
     * The current week, Sunday through Saturday.
     *
     * @return array{start: string, end: string}
     */
    public static function defaultWeek(?string $timezone = null): array
    {
        $today = CarbonImmutable::now($timezone ?? 'UTC');

        return [
            'start' => $today->startOfWeek(CarbonImmutable::SUNDAY)->toDateString(),
            'end' => $today->endOfWeek(CarbonImmutable::SATURDAY)->toDateString(),
        ];
    }

    /**
     * Normalize a submitted range, swapping reversed dates and falling back to
     * the current week when either date is missing or invalid.
     *
     * @param  array<string, mixed>  $range
     * @return array{start: string, end: string}
     */
    public static function normalize(array $range, ?string $timezone = null): array
    {
        $start = self::dateKey($range['start'] ?? null);
        $end = self::dateKey($range['end'] ?? null);

        if ($start === null || $end === null) {
            return self::defaultWeek($timezone);
        }

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Validate a `Y-m-d` date string.
     */
    public static function dateKey(mixed $value): ?string
    {
        if (! \is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year) ? $value : null;
    }
}
