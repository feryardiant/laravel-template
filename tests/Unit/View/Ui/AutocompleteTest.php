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

it('renders a plain hidden field for the selected value', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.autocomplete name="state" value="Alaska" label="State" />');

    expect(hiddenFormInputCount((string) $view, 'state', 'Alaska'))->toBe(1);
});

it('renders the localized clear action', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.autocomplete name="state" value="Alaska" label="State" clearable />');

    $view->assertSee('Kosongkan isian');
});

it('marks the field invalid when its model has errors', function () {
    /** @var TestCase $this */
    $this->withViewErrors(['state' => 'The state field is required.']);

    $view = $this->blade('<x-ui.autocomplete wire:model="state" label="State" />');

    $view->assertSee('data-invalid', false)
        ->assertSee('aria-invalid="true"', false);
});
