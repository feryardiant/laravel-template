<?php

declare(strict_types=1);

namespace App\View;

use DateTimeImmutable;
use DateTimeInterface;
use Flux\DateRange;

/**
 * Presentation-only adapter for calendar, time and datetime values.
 *
 * It normalizes incoming PHP/Blade values into the string shapes consumed by the
 * Alpine components. It never performs date math, timezone conversion or
 * validation policy; date objects use the timezone they already carry.
 */
final class Value
{
    /**
     * Normalize a value to a strict `Y-m-d` key.
     *
     * Date objects use their own timezone. Date-time strings and invalid dates
     * return `null`.
     */
    public static function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! \is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false) {
            return null;
        }

        $errors = DateTimeImmutable::getLastErrors();

        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        return $date->format('Y-m-d') === $value ? $value : null;
    }

    /**
     * Normalize a DateRange, comma-separated string or array to unique `Y-m-d`
     * keys.
     *
     * @return array<int, string>
     */
    public static function dates(mixed $value): array
    {
        if ($value instanceof DateRange) {
            return array_values(array_filter([
                self::date($value->start()),
                self::date($value->end()),
            ]));
        }

        if (\is_string($value)) {
            $value = explode(',', $value);
        }

        if (! \is_array($value)) {
            return [];
        }

        $dates = array_map(self::date(...), $value);

        return array_values(array_unique(array_filter($dates)));
    }

    /**
     * Normalize a DateRange, `start/end` string or array to nullable endpoint
     * dates.
     *
     * @return array{start: ?string, end: ?string}
     */
    public static function range(mixed $value): array
    {
        if ($value instanceof DateRange) {
            return [
                'start' => self::date($value->start()),
                'end' => self::date($value->end()),
            ];
        }

        if (\is_string($value)) {
            [$start, $end] = array_pad(explode('/', $value, 2), 2, null);

            return [
                'start' => self::date($start),
                'end' => self::date($end),
            ];
        }

        if (\is_array($value)) {
            return [
                'start' => self::date($value['start'] ?? null),
                'end' => self::date($value['end'] ?? null),
            ];
        }

        return ['start' => null, 'end' => null];
    }

    /**
     * Normalize a value to a strict `H:i` wall-clock time.
     *
     * Date objects use their own timezone; seconds are dropped. Invalid values
     * return `null`.
     */
    public static function time(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i');
        }

        if (! \is_string($value)) {
            return null;
        }

        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', trim($value), $matches)) {
            return null;
        }

        return $matches[1].':'.$matches[2];
    }

    /**
     * Normalize a comma-separated string or array to unique `H:i` values.
     *
     * @return array<int, string>
     */
    public static function times(mixed $value): array
    {
        if (\is_string($value)) {
            $value = explode(',', $value);
        }

        if (! \is_array($value)) {
            return [];
        }

        $times = array_map(self::time(...), $value);

        return array_values(array_unique(array_filter($times)));
    }

    /**
     * Normalize an array or `start/end` string to nullable `H:i` endpoints.
     *
     * @return array{start: ?string, end: ?string}
     */
    public static function timeRange(mixed $value): array
    {
        if (\is_string($value)) {
            $parts = explode('/', $value, 2);

            return [
                'start' => self::time($parts[0]),
                'end' => self::time($parts[1] ?? null),
            ];
        }

        if (! \is_array($value)) {
            return ['start' => null, 'end' => null];
        }

        return [
            'start' => self::time($value['start'] ?? null),
            'end' => self::time($value['end'] ?? null),
        ];
    }

    /**
     * Normalize time and inclusive `H:i-H:i` range entries, dropping invalid
     * values.
     *
     * @return array<int, string>
     */
    public static function timeRanges(mixed $value): array
    {
        if (\is_string($value)) {
            $value = explode(',', $value);
        }

        if (! \is_array($value)) {
            return [];
        }

        $ranges = [];

        foreach ($value as $entry) {
            if (! \is_string($entry)) {
                continue;
            }

            if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?(?:\s*-\s*([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?)?$/', trim($entry), $matches)) {
                continue;
            }

            $start = $matches[1].':'.$matches[2];
            $end = isset($matches[3], $matches[4]) ? $matches[3].':'.$matches[4] : $start;

            $ranges[] = $start === $end ? $start : $start.'-'.$end;
        }

        return array_values(array_unique($ranges));
    }

    /**
     * Normalize a value to `Y-m-d\TH:i`.
     *
     * Date-only values become midnight. Date objects use their own timezone;
     * invalid values return `null`.
     */
    public static function datetime(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i');
        }

        if (! \is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^(\d{4}-\d{2}-\d{2})[T ]([01]\d|2[0-3]):([0-5]\d)$/', $value, $matches)) {
            $date = self::date($matches[1]);

            return $date === null ? null : $date.'T'.$matches[2].':'.$matches[3];
        }

        $date = self::date($value);

        return $date === null ? null : $date.'T00:00';
    }

    /**
     * Normalize a DateRange, `start/end` string or array to nullable endpoint
     * datetimes.
     *
     * @return array{start: ?string, end: ?string}
     */
    public static function datetimeRange(mixed $value): array
    {
        if ($value instanceof DateRange) {
            return [
                'start' => self::datetime($value->start()),
                'end' => self::datetime($value->end()),
            ];
        }

        if (\is_string($value)) {
            [$start, $end] = array_pad(explode('/', $value, 2), 2, null);

            return [
                'start' => self::datetime($start),
                'end' => self::datetime($end),
            ];
        }

        if (\is_array($value)) {
            return [
                'start' => self::datetime($value['start'] ?? null),
                'end' => self::datetime($value['end'] ?? null),
            ];
        }

        return ['start' => null, 'end' => null];
    }

    /**
     * Normalize unavailable entries.
     *
     * Accepts dates, times, time ranges, datetimes and one-day datetime ranges;
     * invalid entries are dropped.
     *
     * @return array<int, string>
     */
    public static function datetimes(mixed $value): array
    {
        if (\is_string($value)) {
            $value = explode(',', $value);
        }

        if (! \is_array($value)) {
            return [];
        }

        $entries = [];

        foreach ($value as $entry) {
            if ($entry instanceof DateTimeInterface) {
                $entries[] = $entry->format('Y-m-d\TH:i');

                continue;
            }

            if (! \is_string($entry)) {
                continue;
            }

            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            if (preg_match('/^(\d{4}-\d{2}-\d{2})[T ]([01]\d|2[0-3]):([0-5]\d)-([01]\d|2[0-3]):([0-5]\d)$/', $entry, $matches) && ($date = self::date($matches[1])) !== null) {
                $entries[] = $date.'T'.$matches[2].':'.$matches[3].'-'.$matches[4].':'.$matches[5];

                continue;
            }

            if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)-([01]\d|2[0-3]):([0-5]\d)$/', $entry, $matches)) {
                $entries[] = $matches[1].':'.$matches[2].'-'.$matches[3].':'.$matches[4];

                continue;
            }

            if (($date = self::date($entry)) !== null) {
                $entries[] = $date;

                continue;
            }

            if (($datetime = self::datetime($entry)) !== null) {
                $entries[] = $datetime;

                continue;
            }

            if (($time = self::time($entry)) !== null) {
                $entries[] = $time;
            }
        }

        return array_values(array_unique($entries));
    }

    /**
     * Normalize a value to the shape expected by a calendar mode.
     *
     * `single` returns a date key, `multiple` a list of keys, and `range` a
     * nullable endpoint array.
     */
    public static function calendar(mixed $value, string $mode): mixed
    {
        return match ($mode) {
            'multiple' => self::dates($value),
            'range' => self::range($value),
            default => self::date($value),
        };
    }
}
