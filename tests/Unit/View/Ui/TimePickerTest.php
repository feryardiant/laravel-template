<?php

declare(strict_types=1);

use Tests\TestCase;

it('renders a single time field with its placeholder', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.time-picker name="reminder_at" label="Reminder at" />');

    $view->assertSee('Reminder at')
        ->assertSee(__('ui.time-picker.placeholder'))
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

    $view->assertSee(__('ui.time-picker.placeholder-range'))
        ->assertSee('name="window[start]"', false)
        ->assertSee('name="window[end]"', false);
});

it('renders the localized clear and range actions', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.time-picker name="window" mode="range" label="Window" clearable />');

    $view->assertSee(__('ui.time-picker.clear'))
        ->assertSee(__('ui.time-picker.add-start'))
        ->assertSee(__('ui.time-picker.add-end'));
});

it('renders the invalid state', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.time-picker name="reminder_at" invalid />');

    $view->assertSee('data-invalid', false)
        ->assertSee('aria-invalid="true"', false);
});

it('applies the class attribute to the control root', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.time-picker label="Reminder" class="w-full sm:w-48" />');

    expect((string) $view)->toMatch('/data-ui-time-picker[^>]*sm:w-48/');
});

it('renders a masked typed time input', function () {
    /** @var TestCase $this */
    $view = (string) $this->blade('<x-ui.time-picker with-inputs label="Time" />');

    expect($view)->toContain('data-ui-trigger')
        ->toContain('x-mask="99:99"');
});
