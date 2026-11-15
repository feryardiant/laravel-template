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
