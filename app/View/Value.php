<?php

declare(strict_types=1);

namespace App\View;

use DateTimeImmutable;
use DateTimeInterface;
use Flux\DateRange;

final class Value
{
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

    public static function calendar(mixed $value, string $mode): mixed
    {
        return match ($mode) {
            'multiple' => self::dates($value),
            'range' => self::range($value),
            default => self::date($value),
        };
    }
}
