<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Enums\Role;
use App\Livewire\OrganizationSwitcher;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('the organization switcher lists active memberships and switches the session', function () {
    $first = Organization::factory()->create(['name' => 'First Org']);
    $second = Organization::factory()->create(['name' => 'Second Org']);

    $user = actingAsMember($first, Role::Member);
    app(CreateMember::class)->attach($second, $user->person, startDate: now(), role: Role::Member);

    get(route('dashboard'))
        ->assertOk()
        ->assertSee('First Org')
        ->assertSee('Second Org');

    Livewire::test(OrganizationSwitcher::class)
        ->call('switchOrganization', $second->getKey())
        ->assertRedirect();

    expect(session(CurrentOrganization::SESSION_KEY))->toBe($second->getKey());
});

test('switching to an organization the user does not belong to is forbidden', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $outside = Organization::factory()->create();

    Livewire::test(OrganizationSwitcher::class)
        ->call('switchOrganization', $outside->getKey())
        ->assertForbidden();

    expect(session(CurrentOrganization::SESSION_KEY))->toBe($organization->getKey());
});

test('plain members do not see the organization settings item', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    get(route('dashboard'))->assertOk()->assertDontSee(__('Organization settings'));
});

test('owners and admins see the organization settings item', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    get(route('dashboard'))->assertOk()->assertSee(__('Organization settings'));
});
