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

    public static function calendar(mixed $value, string $mode): mixed
    {
        return match ($mode) {
            'multiple' => self::dates($value),
            'range' => self::range($value),
            default => self::date($value),
        };
    }
}
