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

    $view->assertSee('name="industry"', false)
        ->assertSee('value="web"', false);
});

it('renders localized clear and disabled states', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.select name="industry" value="web" label="Industry" clearable disabled />');

    $view->assertSee('Kosongkan pilihan')
        ->assertSee('data-disabled', false)
        ->assertSee('disabled', false);
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
