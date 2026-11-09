<?php

declare(strict_types=1);

use Tests\TestCase;

it('renders a labeled listbox with its options', function () {
    /** @var TestCase $this */
    $view = $this->blade(<<<'BLADE'
        <x-ui.select label="Industry" placeholder="Choose industry...">
            <x-ui.select.option value="web">Web development</x-ui.select.option>
            <x-ui.select.option value="design">Design services</x-ui.select.option>
        </x-ui.select>
    BLADE);

    $view->assertSee('Industry')
        ->assertSee('Choose industry...')
        ->assertSee('data-ui-select', false)
        ->assertSee('data-variant="listbox"', false)
        ->assertSee('data-ui-option', false)
        ->assertSee('data-value="web"', false)
        ->assertSee('Web development')
        ->assertSee('Design services');
});

it('renders a plain hidden field for the selected value', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.select name="industry" value="web" label="Industry" />');

    expect(hiddenFormInputCount((string) $view, 'industry', 'web'))->toBe(1);
});

it('renders localized clear and disabled states', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.select name="industry" value="web" label="Industry" clearable disabled />');

    $view->assertSee('Kosongkan pilihan')
        ->assertSee('data-disabled', false)
        ->assertSee('disabled', false)
        // A disabled picker must not submit its hidden value.
        ->assertSeeInOrder(['type="hidden"', 'name="industry"', 'value="web"', 'disabled'], false);
});

it('renders the pillbox variant with a searchable trigger', function () {
    /** @var TestCase $this */
    $view = $this->blade(<<<'BLADE'
        <x-ui.select variant="pillbox" label="Tags" placeholder="Choose tags...">
            <x-ui.select.option value="design">Design</x-ui.select.option>
            <x-ui.select.option value="marketing">Marketing</x-ui.select.option>
        </x-ui.select>
    BLADE);

    $view->assertSee('data-variant="pillbox"', false)
        ->assertSee('role="combobox"', false)
        ->assertSee('Design')
        ->assertSee('Marketing');
});

it('uses the placeholder as its accessible name', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.select placeholder="Choose something" />');

    $view->assertSee('aria-label="Choose something"', false);
});

it('falls back to the generic accessible name', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.select />');

    $view->assertSee('aria-label="Pilih"', false);
});

it('renders the invalid state', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.select name="industry" value="web" invalid />');

    $view->assertSee('data-invalid', false)
        ->assertSee('aria-invalid="true"', false);
});

it('generates unique ids for duplicate models', function () {
    /** @var TestCase $this */
    $this->withViewErrors([]);

    $view = $this->blade(<<<'BLADE'
        <div>
            <x-ui.select wire:model="industry">
                <x-ui.select.option value="web">Web</x-ui.select.option>
            </x-ui.select>
            <x-ui.select wire:model="industry">
                <x-ui.select.option value="web">Web</x-ui.select.option>
            </x-ui.select>
        </div>
    BLADE);

    $html = (string) $view;

    preg_match_all('/id="(ui-select-[^"]+-trigger)"/', $html, $triggers);
    preg_match_all('/aria-controls="(ui-select-[^"]+-listbox)"/', $html, $controls);

    expect($triggers[1])->toHaveCount(2)
        ->and(array_unique($triggers[1]))->toHaveCount(2)
        ->and(array_unique($controls[1]))->toHaveCount(2);
});

it('sits bare inside a Flux field', function () {
    /** @var TestCase $this */
    $view = $this->blade(<<<'BLADE'
        <flux:field>
            <flux:label>Nested field</flux:label>
            <x-ui.select name="nested_industry" placeholder="Pick one">
                <x-ui.select.option value="design">Design services</x-ui.select.option>
            </x-ui.select>
            <flux:description>Outer field description</flux:description>
        </flux:field>
    BLADE);

    $html = (string) $view;

    expect(substr_count($html, 'Nested field'))->toBe(1)
        ->and(substr_count($html, 'Outer field description'))->toBe(1);
});
