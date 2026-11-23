<?php

declare(strict_types=1);

use App\Support\DateRange;
use Carbon\CarbonImmutable;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('returns the current week', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-25 12:00:00', 'UTC'));

    expect(DateRange::defaultWeek('UTC'))->toBe([
        'start' => '2026-11-22',
        'end' => '2026-11-28',
    ]);
});

it('uses the given timezone for week boundaries', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-28 17:00:00', 'UTC'));

    expect(DateRange::defaultWeek('Asia/Jakarta'))->toBe([
        'start' => '2026-11-29',
        'end' => '2026-12-05',
    ]);
});

it('swaps a reversed range', function () {
    expect(DateRange::normalize([
        'start' => '2026-11-28',
        'end' => '2026-11-22',
    ]))->toBe([
        'start' => '2026-11-22',
        'end' => '2026-11-28',
    ]);
});

it('falls back to the current week for missing or invalid dates', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-25 12:00:00', 'UTC'));

    expect(DateRange::normalize(['start' => '2026-02-30', 'end' => '2026-11-28'], 'UTC'))
        ->toBe(['start' => '2026-11-22', 'end' => '2026-11-28'])
        ->and(DateRange::normalize([], 'UTC'))
        ->toBe(['start' => '2026-11-22', 'end' => '2026-11-28']);
});

it('validates a date key', function (mixed $value, ?string $expected) {
    expect(DateRange::dateKey($value))->toBe($expected);
})->with([
    'valid date' => ['2026-11-23', '2026-11-23'],
    'leap day' => ['2024-02-29', '2024-02-29'],
    'non-leap day' => ['2026-02-29', null],
    'wrong format' => ['23-11-2026', null],
    'not a string' => [20261123, null],
]);
