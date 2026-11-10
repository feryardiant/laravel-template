<?php

declare(strict_types=1);

use Tests\TestCase;

it('renders a vertical timeline as an ordered list by default', function () {
    /** @var TestCase $this */
    $view = $this->blade(<<<'BLADE'
        <x-ui.timeline>
            <x-ui.timeline.item>Draft created</x-ui.timeline.item>
            <x-ui.timeline.item>Published</x-ui.timeline.item>
        </x-ui.timeline>
    BLADE);

    $view->assertSee('<ol', false)
        ->assertSee('role="list"', false)
        ->assertSee('data-ui-timeline', false)
        ->assertSee('data-mode="vertical"', false)
        ->assertSee('data-align="center"', false)
        ->assertSee('<li', false)
        ->assertSee('data-ui-timeline-item', false)
        ->assertSee('Draft created')
        ->assertSee('Published');
});

it('renders a horizontal timeline with the configured alignment', function () {
    /** @var TestCase $this */
    $view = $this->blade(<<<'BLADE'
        <x-ui.timeline mode="horizontal" align="start">
            <x-ui.timeline.item status="current" indicator:icon="truck">On its way</x-ui.timeline.item>
            <x-ui.timeline.item status="incomplete">Delivered</x-ui.timeline.item>
        </x-ui.timeline>
    BLADE);

    $view->assertSee('data-mode="horizontal"', false)
        ->assertSee('data-align="start"', false)
        ->assertSee('data-status="current"', false)
        ->assertSee('data-status="incomplete"', false)
        ->assertSee('data-flux-icon', false);
});

it('lets an item override the alignment', function () {
    /** @var TestCase $this */
    $view = $this->blade(<<<'BLADE'
        <x-ui.timeline align="center">
            <x-ui.timeline.item align="end">Last line</x-ui.timeline.item>
        </x-ui.timeline>
    BLADE);

    $view->assertSee('data-align="end"', false);
});

it('renders a bare indicator from the item props', function () {
    /** @var TestCase $this */
    $view = $this->blade(<<<'BLADE'
        <x-ui.timeline>
            <x-ui.timeline.item indicator:icon="check" indicator:variant="bare" class:indicator="size-8 text-amber-500">Approved</x-ui.timeline.item>
        </x-ui.timeline>
    BLADE);

    $view->assertSee('data-variant="bare"', false)
        ->assertSee('data-flux-icon', false)
        ->assertSee('size-8 text-amber-500')
        ->assertDontSee('indicator:icon', false)
        ->assertDontSee('indicator:variant', false)
        ->assertDontSee('class:indicator', false);
});

it('announces each item status', function () {
    /** @var TestCase $this */
    app()->setLocale('id');

    $view = $this->blade(<<<'BLADE'
        <x-ui.timeline>
            <x-ui.timeline.item status="complete">Order confirmed</x-ui.timeline.item>
            <x-ui.timeline.item status="current">On its way</x-ui.timeline.item>
            <x-ui.timeline.item status="incomplete">incomplete</x-ui.timeline.item>
        </x-ui.timeline>
    BLADE);

    $view->assertSee(__('ui.timeline.complete'))
        ->assertSee(__('ui.timeline.current'))
        ->assertSee(__('ui.timeline.incomplete'))
        ->assertSee('aria-current="step"', false);
});

it('renders a default dot when no indicator is configured', function () {
    /** @var TestCase $this */
    $view = $this->blade(<<<'BLADE'
        <x-ui.timeline>
            <x-ui.timeline.item>Draft</x-ui.timeline.item>
        </x-ui.timeline>
    BLADE);

    $view->assertSee('data-variant="default"', false)
        ->assertDontSee('data-flux-icon', false)
        ->assertSee('aria-hidden="true"', false);
});

it('falls back to safe values for unknown options', function () {
    /** @var TestCase $this */
    $view = $this->blade(<<<'BLADE'
        <x-ui.timeline mode="diagonal" align="middle">
            <x-ui.timeline.item status="done" align="middle">Event</x-ui.timeline.item>
        </x-ui.timeline>
    BLADE);

    $view->assertSee('data-mode="vertical"', false)
        ->assertSee('data-align="center"', false)
        ->assertDontSee('data-status', false)
        ->assertDontSee('data-align="middle"', false);
});
