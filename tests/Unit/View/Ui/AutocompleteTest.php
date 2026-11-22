<?php

declare(strict_types=1);

use Tests\TestCase;

it('renders a labeled combobox and its local items', function () {
    /** @var TestCase $this */
    $view = $this->blade(<<<'BLADE'
        <x-ui.autocomplete label="State of residence" placeholder="Start typing...">
            <x-ui.autocomplete.item>Alaska</x-ui.autocomplete.item>
            <x-ui.autocomplete.item>California</x-ui.autocomplete.item>
        </x-ui.autocomplete>
    BLADE);

    $view->assertSee('State of residence')
        ->assertSee('Start typing...')
        ->assertSee('data-ui-autocomplete', false)
        ->assertSee('role="combobox"', false)
        ->assertSee('aria-autocomplete="list"', false)
        ->assertSee('aria-haspopup="listbox"', false)
        ->assertSee('aria-controls="ui-autocomplete-', false)
        ->assertSee('Alaska')
        ->assertSee('California');
});

it('forwards required and autofocus to the input', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.autocomplete label="Name" required autofocus />');

    $view->assertSee('required', false)
        ->assertSee('autofocus', false);
});

it('renders a plain hidden field for the selected value', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.autocomplete name="state" value="Alaska" label="State" />');

    expect(hiddenFormInputCount((string) $view, 'state', 'Alaska'))->toBe(1);
});

it('renders the localized clear action', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.autocomplete name="state" value="Alaska" label="State" clearable />');

    $view->assertSee(__('ui.autocomplete.clear'));
});

it('marks the field invalid when its model has errors', function () {
    /** @var TestCase $this */
    $this->withViewErrors(['state' => 'The state field is required.']);

    $view = $this->blade('<x-ui.autocomplete wire:model="state" label="State" />');

    $view->assertSee('data-invalid', false)
        ->assertSee('aria-invalid="true"', false);
});

it('uses the placeholder as its accessible name', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.autocomplete placeholder="Choose a state" />');

    $view->assertSee('aria-label="Choose a state"', false);
});

it('falls back to the generic accessible name', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.autocomplete />');

    $view->assertSee('aria-label="'.__('ui.common.select').'"', false);
});

it('renders disabled and invalid states', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.autocomplete name="state" value="Alaska" disabled invalid />');

    $view->assertSee('data-invalid', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('disabled', false);
});

it('applies the class attribute to the control root', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.autocomplete label="State" class="w-full sm:w-48" />');

    expect((string) $view)->toMatch('/data-ui-autocomplete[^>]*sm:w-48/');
});

it('renders keyboard hints, masks and the copy and visibility controls', function () {
    /** @var TestCase $this */
    $view = $this->blade(<<<'BLADE'
        <div>
            <x-ui.autocomplete label="Search" placeholder="Search..." kbd="⌘K" clearable mask="99/99/9999" />
            <x-ui.autocomplete label="Money" mask:dynamic="$money($input)" />
            <x-ui.autocomplete label="Key" value="ABC" readonly copyable />
            <x-ui.autocomplete label="Password" type="password" viewable />
        </div>
    BLADE);

    $view->assertSee('⌘K')
        ->assertSee('x-mask="99/99/9999"', false)
        ->assertSee('x-mask:dynamic="$money($input)"', false)
        ->assertSee(__('ui.autocomplete.clear'))
        ->assertSee('aria-label="'.__('ui.autocomplete.copy').'"', false)
        ->assertSee('aria-label="'.__('ui.autocomplete.show-password').'"', false);
});
