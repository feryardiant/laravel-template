<?php

declare(strict_types=1);

use Tests\TestCase;

it('renders a manual popover shell around its slot', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.popover id="menu">Content</x-ui.popover>');

    $view->assertSee('id="menu"', false)
        ->assertSee('popover="manual"', false)
        ->assertSee('data-ui-popover', false)
        ->assertSee('z-50', false)
        ->assertDontSee('role=', false)
        ->assertDontSee('aria-modal', false)
        ->assertDontSee('aria-label', false)
        ->assertSee('Content');
});

it('marks a dialog popover as non-modal and labeled', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.popover id="picker" role="dialog" label="Choose a date" class="w-72">Content</x-ui.popover>');

    $view->assertSee('role="dialog"', false)
        ->assertSee('aria-modal="false"', false)
        ->assertSee('aria-label="Choose a date"', false)
        ->assertSee('w-72', false)
        ->assertSee('z-50', false);
});

it('does not mark non-dialog popovers as modal', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.popover id="list" role="listbox" label="Options">Content</x-ui.popover>');

    $view->assertSee('role="listbox"', false)
        ->assertSee('aria-label="Options"', false)
        ->assertDontSee('aria-modal', false);
});
