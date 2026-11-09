<?php

declare(strict_types=1);

use Tests\TestCase;

it('renders a single time field with its placeholder', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.time-picker name="reminder_at" label="Reminder at" />');

    $view->assertSee('Reminder at')
        ->assertSee('Pilih waktu')
        ->assertSee('data-ui-time-picker', false)
        ->assertSee('role="combobox"', false)
        ->assertSee('aria-haspopup="dialog"', false);
});

it('renders a plain hidden field for a single time', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.time-picker name="reminder_at" value="09:30" label="Reminder at" />');

    $view->assertSee('name="reminder_at"', false)
        ->assertSee('value="09:30"', false);
});

it('renders start and end fields for a range', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.time-picker name="window" mode="range" label="Window" />');

    $view->assertSee('Pilih rentang waktu')
        ->assertSee('name="window[start]"', false)
        ->assertSee('name="window[end]"', false);
});

it('renders the localized clear and range actions', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.time-picker name="window" mode="range" label="Window" clearable />');

    $view->assertSee('Kosongkan waktu')
        ->assertSee('Tambah awal')
        ->assertSee('Tambah akhir');
});
