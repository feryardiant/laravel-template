<?php

declare(strict_types=1);

use Tests\TestCase;

it('renders a single datetime field with its placeholder', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.datetime-picker name="starts_at" label="Starts at" />');

    $view->assertSee('Starts at')
        ->assertSee(__('ui.datetime-picker.placeholder'))
        ->assertSee('data-ui-datetime-picker', false)
        ->assertSee('data-mode="single"', false)
        ->assertSee('role="combobox"', false);
});

it('renders a plain hidden field for a single datetime', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.datetime-picker name="starts_at" value="2026-11-09T09:30" label="Starts at" />');

    $view->assertSee('name="starts_at"', false)
        ->assertSee('value="2026-11-09T09:30"', false);
});

it('renders start and end fields for a range', function () {
    /** @var TestCase $this */
    $view = $this->blade(
        '<x-ui.datetime-picker name="schedule" mode="range" :value="[\'start\' => \'2026-11-09T09:30\', \'end\' => \'2026-11-12T11:00\']" label="Schedule" />',
    );

    $view->assertSee(__('ui.datetime-picker.placeholder-range'))
        ->assertSee('data-mode="range"', false)
        ->assertSee('name="schedule[start]"', false)
        ->assertSee('value="2026-11-09T09:30"', false)
        ->assertSee('name="schedule[end]"', false)
        ->assertSee('value="2026-11-12T11:00"', false);
});

it('renders the localized clear, time labels and footer', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.datetime-picker name="schedule" mode="range" label="Schedule" clearable />');

    $view->assertSee(__('ui.datetime-picker.clear'))
        ->assertSee(__('ui.time-picker.hour'))
        ->assertSee(__('ui.time-picker.minute'))
        ->assertSee(__('ui.date-picker.cancel'))
        ->assertSee(__('ui.date-picker.select-range'));
});

it('marks the field invalid when its model has errors', function () {
    /** @var TestCase $this */
    $this->withViewErrors(['starts_at' => 'The starts at field is required.']);

    $view = $this->blade('<x-ui.datetime-picker wire:model="starts_at" label="Starts at" />');

    $view->assertSee('data-invalid', false)
        ->assertSee('aria-invalid="true"', false);
});

it('keeps the time of an open-to datetime object', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.datetime-picker :open-to="$when" />', [
        'when' => new DateTimeImmutable('2026-11-15 14:30'),
    ]);

    $view->assertSee('2026-11-15', false)
        ->assertSee('14:30', false);
});

it('renders the invalid state', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.datetime-picker name="starts_at" invalid />');

    $view->assertSee('data-invalid', false)
        ->assertSee('aria-invalid="true"', false);
});
