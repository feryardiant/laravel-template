<?php

declare(strict_types=1);

use App\View\Value;
use Carbon\Carbon;
use Flux\DateRange;

it('formats single dates and rejects invalid keys', function () {
    expect(Value::date('2026-11-08'))->toBe('2026-11-08')
        ->and(Value::date('2026-02-30'))->toBeNull()
        ->and(Value::date('08/11/2026'))->toBeNull()
        ->and(Value::date(null))->toBeNull();
});

it('formats Carbon in its own timezone without converting to UTC', function () {
    $date = Carbon::parse('2026-11-08 23:30:00', 'Asia/Jakarta');

    expect(Value::date($date))->toBe('2026-11-08');
});

it('normalizes multiple dates and removes duplicates', function () {
    expect(Value::dates('2026-11-08, 2026-11-09'))->toBe(['2026-11-08', '2026-11-09'])
        ->and(Value::dates(['2026-11-08', '2026-11-08', 'invalid']))->toBe(['2026-11-08']);
});

it('normalizes string, array and DateRange ranges', function () {
    expect(Value::range('2026-11-01/2026-11-05'))->toBe([
        'start' => '2026-11-01',
        'end' => '2026-11-05',
    ])->and(Value::range(['start' => '2026-11-01', 'end' => null]))->toBe([
        'start' => '2026-11-01',
        'end' => null,
    ])->and(Value::range(new DateRange(
        Carbon::parse('2026-11-01'),
        Carbon::parse('2026-11-05'),
    )))->toBe([
        'start' => '2026-11-01',
        'end' => '2026-11-05',
    ]);
});

it('normalizes a calendar value by mode', function () {
    expect(Value::calendar('2026-11-08', 'single'))->toBe('2026-11-08')
        ->and(Value::calendar(['2026-11-08', '2026-11-09'], 'multiple'))->toBe([
            '2026-11-08',
            '2026-11-09',
        ])
        ->and(Value::calendar('2026-11-01/2026-11-05', 'range'))->toBe([
            'start' => '2026-11-01',
            'end' => '2026-11-05',
        ]);
});
