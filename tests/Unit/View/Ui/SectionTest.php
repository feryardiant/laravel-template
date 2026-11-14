<?php

declare(strict_types=1);

use Tests\TestCase;

it('renders a titled section with its items', function () {
    /** @var TestCase $this */
    $view = $this->blade(<<<'BLADE'
        <x-ui.section title="Danger Zone">
            <x-ui.section.item title="Delete account" description="Remove everything">
                <button>Delete</button>
            </x-ui.section.item>
        </x-ui.section>
    BLADE);

    $view->assertSee('Danger Zone')
        ->assertSee('Delete account')
        ->assertSee('Remove everything')
        ->assertSee('divide-zinc-200', false)
        ->assertDontSee('divide-red-200', false);
});

it('renders the danger variant', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.section variant="danger" title="Danger Zone" />');

    $view->assertSee('divide-red-200', false)
        ->assertDontSee('divide-zinc-200', false);
});

it('renders an item without a title', function () {
    /** @var TestCase $this */
    $view = $this->blade(<<<'BLADE'
        <x-ui.section>
            <x-ui.section.item>
                <input type="checkbox">
            </x-ui.section.item>
        </x-ui.section>
    BLADE);

    $view->assertSee('type="checkbox"', false)
        ->assertDontSee('<flux:heading', false);
});
