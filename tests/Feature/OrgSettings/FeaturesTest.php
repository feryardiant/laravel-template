<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\OrgSettings\Features;
use App\Models\Organization;
use App\Models\User;
use App\Support\Feature\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('toggles registration for the root tenant', function () {
    $root = Organization::factory()->asRootTenant()->create();

    actingAsMember($root, Role::Owner);

    Livewire::test(Features::class)
        ->assertSet('registration', false)
        ->set('registration', true)
        ->assertHasNoErrors();

    expect(Registration::enabled())->toBeTrue();

    Livewire::test(Features::class)
        ->assertSet('registration', true)
        ->set('registration', false)
        ->assertHasNoErrors();

    expect(Registration::enabled())->toBeFalse();
});

it('shows the features tab only to the root tenant', function () {
    $root = Organization::factory()->asRootTenant()->create();
    $tenant = Organization::factory()->asTenantOf($root)->create();

    actingAsMember($root, Role::Owner);

    get(route('org-settings.general'))->assertSee(__('Features'));

    actingAsMember($tenant, Role::Owner);

    get(route('org-settings.general'))->assertDontSee(__('Features'));
});

it('is not available to non-root tenants', function () {
    $root = Organization::factory()->asRootTenant()->create();
    $tenant = Organization::factory()->asTenantOf($root)->create();

    actingAsMember($tenant, Role::Owner);

    get(route('org-settings.features'))->assertNotFound();
});

it('is not available without an organization', function () {
    actingAs(User::factory()->withProfile()->create());

    get(route('org-settings.features'))->assertNotFound();
});

it('forbids plain members of the root tenant', function () {
    $root = Organization::factory()->asRootTenant()->create();

    actingAsMember($root, Role::Member);

    Livewire::test(Features::class)->assertForbidden();
});
