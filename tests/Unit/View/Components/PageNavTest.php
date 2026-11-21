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
        ->assertSee('sm:w-1/5 md:w-3xs', false)
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

it('applies attributes from the actions slot', function () {
    /** @var TestCase $this */
    $this->blade(<<<'BLADE'
        <x-page.nav heading="Profile">
            <x-slot:actions class="w-full sm:w-48"><button>Save</button></x-slot:actions>
        </x-page.nav>
    BLADE)
        ->assertSee('@5xl/main:shrink-0', false)
        ->assertSee('w-full sm:w-48', false)
        ->assertSee('<button>Save</button>', false);
});

it('applies header:class to the header only', function () {
    /** @var TestCase $this */
    $this->blade('<x-page.nav heading="Profile" header:class="gap-8">Body</x-page.nav>')
        ->assertSee('<header class="flex flex-col gap-4 @5xl/main:flex-row @5xl/main:items-start @5xl/main:justify-between gap-8">', false)
        ->assertDontSee('header:class="gap-8"', false);
});

it('uses a container query for the header layout', function () {
    /** @var TestCase $this */
    $this->blade('<x-page.nav heading="Profile">Body</x-page.nav>')
        ->assertSee('@container/main', false)
        ->assertSee('@5xl/main:flex-row', false)
        ->assertDontSee('md:flex-row', false);
});
