<?php

declare(strict_types=1);

use Tests\TestCase;

it('renders the nav column, heading, actions, and body', function () {
    /** @var TestCase $this */
    $this->blade(<<<'BLADE'
        <x-page.nav heading="Profile" subheading="Your details" width="max-w-none">
            <x-slot:nav><a href="/settings">Profile tab</a></x-slot:nav>
            <x-slot:actions><button>Save</button></x-slot:actions>
            <p>Body</p>
        </x-page.nav>
    BLADE)
        ->assertSee('<h2', false)
        ->assertSee('Profile tab')
        ->assertSee('Your details')
        ->assertSee('Save')
        ->assertSee('Body')
        ->assertSee('md:w-[220px]', false)
        ->assertSee('max-w-none', false)
        ->assertDontSee('max-w-lg', false);
});

it('renders without nav, actions, or width overrides', function () {
    /** @var TestCase $this */
    $this->blade('<x-page.nav heading="General">Body</x-page.nav>')
        ->assertSee('<h2', false)
        ->assertSee('General')
        ->assertSee('Body')
        ->assertSee('max-w-lg', false);
});
