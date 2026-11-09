<?php

declare(strict_types=1);

use Tests\TestCase;

it('renders a plain field for a single date', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.calendar name="date" value="2026-11-09" />');

    $view->assertSee('data-ui-calendar', false)
        ->assertSee('data-mode="single"', false)
        ->assertSee('name="date"', false)
        ->assertSee('value="2026-11-09"', false);
});

it('renders start and end fields for a range', function () {
    /** @var TestCase $this */
    $view = $this->blade(
        '<x-ui.calendar name="range" mode="range" :value="[\'start\' => \'2026-11-09\', \'end\' => \'2026-11-12\']" />',
    );

    $view->assertSee('data-mode="range"', false)
        ->assertSee('name="range[start]"', false)
        ->assertSee('name="range[end]"', false)
        ->assertSee('2026-11-09', false)
        ->assertSee('2026-11-12', false);
});

it('renders localized navigation and the today shortcut', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.calendar name="date" with-today />');

    $view->assertSee('Bulan sebelumnya')
        ->assertSee('Bulan berikutnya')
        ->assertSee('Tahun')
        ->assertSee('Hari ini');
});

it('renders the invalid state', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.calendar name="date" value="2026-11-09" invalid />');

    $view->assertSee('data-invalid', false)
        ->assertSee('data-invalid:ring-2', false);
});
