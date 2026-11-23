<?php

declare(strict_types=1);

use Tests\TestCase;

it('renders a single date field with its placeholder', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.date-picker name="publish_date" label="Publish date" />');

    $view->assertSee('Publish date')
        ->assertSee(__('ui.date-picker.placeholder'))
        ->assertSee('data-ui-date-picker', false)
        ->assertSee('data-mode="single"', false)
        ->assertSee('role="combobox"', false)
        ->assertSee('aria-haspopup="dialog"', false);
});

it('renders a plain hidden field for a single date', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.date-picker name="publish_date" value="2026-11-09" label="Publish date" />');

    $view->assertSee('name="publish_date"', false)
        ->assertSee('value="2026-11-09"', false);
});

it('renders start and end fields for a range', function () {
    /** @var TestCase $this */
    $view = $this->blade(
        '<x-ui.date-picker name="period" mode="range" :value="[\'start\' => \'2026-11-09\', \'end\' => \'2026-11-12\']" label="Period" />',
    );

    $view->assertSee(__('ui.date-picker.placeholder-range'))
        ->assertSee('data-mode="range"', false)
        ->assertSee('name="period[start]"', false)
        ->assertSee('value="2026-11-09"', false)
        ->assertSee('name="period[end]"', false)
        ->assertSee('value="2026-11-12"', false);
});

it('renders the presets, clear action and footer labels', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.date-picker name="period" mode="range" label="Period" clearable with-presets />');

    $view->assertSee(__('ui.date-picker.presets'))
        ->assertSee(__('ui.date-picker.clear'))
        ->assertSee(__('ui.date-picker.cancel'))
        ->assertSee(__('ui.date-picker.select-range'));
});

it('renders the invalid state', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.date-picker name="publish_date" invalid />');

    $view->assertSee('data-invalid', false)
        ->assertSee('aria-invalid="true"', false);
});

it('applies the class attribute to the control root', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.date-picker label="Publish date" class="w-full sm:w-48" />');

    expect((string) $view)->toMatch('/data-ui-date-picker[^>]*sm:w-48/');
});

it('renders masked typed date inputs', function () {
    /** @var TestCase $this */
    $single = (string) $this->blade('<x-ui.date-picker with-inputs label="Date" />');
    $range = (string) $this->blade('<x-ui.date-picker mode="range" with-inputs label="Range" />');

    expect(substr_count($single, 'x-mask="9999-99-99"'))->toBe(1)
        ->and($single)->toContain('data-ui-trigger')
        ->and(substr_count($range, 'x-mask="9999-99-99"'))->toBe(2)
        ->and($range)->toContain('data-ui-end-trigger');
});
