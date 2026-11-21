<?php

declare(strict_types=1);

use Tests\TestCase;

it('renders a heading, subheading, and separator by default', function () {
    /** @var TestCase $this */
    $this->blade('<x-page heading="Contacts" subheading="All contacts" />')
        ->assertSee('<h1', false)
        ->assertSee('Contacts')
        ->assertSee('All contacts')
        ->assertSee('data-flux-separator', false);
});

it('accepts rich heading and subheading slots', function () {
    /** @var TestCase $this */
    $this->blade(<<<'BLADE'
        <x-page>
            <x-slot:heading>Jane <span>Owner</span></x-slot:heading>
            <x-slot:subheading><flux:subheading class="flex">jane@example.com</flux:subheading></x-slot:subheading>
        </x-page>
    BLADE)
        ->assertSee('Jane')
        ->assertSee('<span>Owner</span>', false)
        ->assertSee('jane@example.com')
        ->assertSee('data-flux-subheading', false);
});

it('omits the separator when disabled and renders actions', function () {
    /** @var TestCase $this */
    $this->blade(<<<'BLADE'
        <x-page heading="Home" :separator="false">
            <x-slot:actions><button>Add</button></x-slot:actions>
        </x-page>
    BLADE)
        ->assertSee('Add')
        ->assertDontSee('data-flux-separator', false);
});

it('applies attributes from the actions slot', function () {
    /** @var TestCase $this */
    $this->blade(<<<'BLADE'
        <x-page heading="Home" :separator="false">
            <x-slot:actions class="w-full sm:w-48"><button>Add</button></x-slot:actions>
        </x-page>
    BLADE)
        ->assertSee('min-w-0 @4xl/page:shrink-0', false)
        ->assertSee('w-full sm:w-48', false)
        ->assertSee('<button>Add</button>', false);
});

it('applies header:class to the header only', function () {
    /** @var TestCase $this */
    $this->blade('<x-page heading="Home" header:class="gap-8">Body</x-page>')
        ->assertSee('<header class="flex flex-col gap-4 @4xl/page:flex-row @4xl/page:items-start @4xl/page:justify-between gap-8">', false)
        ->assertDontSee('header:class="gap-8"', false);
});

it('uses a container query for the header layout', function () {
    /** @var TestCase $this */
    $this->blade('<x-page heading="Home">Body</x-page>')
        ->assertSee('@container/page', false)
        ->assertSee('@4xl/page:flex-row', false)
        ->assertDontSee('sm:flex-row', false);
});
