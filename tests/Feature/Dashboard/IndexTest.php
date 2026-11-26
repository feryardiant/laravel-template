<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('the dashboard exposes a single level one heading without an organization', function () {
    actingAs(User::factory()->create(['locale' => Locale::Indonesian]));

    $html = get(route('dashboard'))->assertOk()->getContent();

    preg_match_all('/<h1\b[^>]*>(.*?)<\/h1>/s', $html, $matches);

    expect($matches[1])->toHaveCount(1)
        ->and(trim(strip_tags($matches[1][0])))->toBe(__("You're not part of an organization yet."));
});

test('users without an organization get the header layout', function () {
    actingAs(User::factory()->create());

    get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('Home'))
        ->assertDontSee(__('Members'));
});

test('members get the sidebar layout with the workspace navigation', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('Home'))
        ->assertSee(__('Members'))
        ->assertSee(__('Contacts'));
});
