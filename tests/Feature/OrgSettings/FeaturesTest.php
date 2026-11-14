<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Enums\Role;
use App\Livewire\OrgSettings\Features;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use App\Support\Feature\ContactDeletion;
use App\Support\Feature\ContactGender;
use App\Support\Feature\MemberDeletion;
use App\Support\Feature\ProfileSelfEdit;
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
        ->assertSet('isRootTenant', true)
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

it('toggles contact gender for any tenant', function () {
    $root = Organization::factory()->asRootTenant()->create();
    $tenant = Organization::factory()->asTenantOf($root)->create();

    actingAsMember($tenant, Role::Owner);

    Livewire::test(Features::class)
        ->assertSet('isRootTenant', false)
        ->assertSet('contactGender', false)
        ->set('contactGender', true)
        ->assertHasNoErrors();

    expect(ContactGender::enabled($tenant))->toBeTrue();

    Livewire::test(Features::class)
        ->assertSet('contactGender', true)
        ->set('contactGender', false)
        ->assertHasNoErrors();

    expect(ContactGender::enabled($tenant))->toBeFalse();
});

it('enables member and contact deletion by default', function () {
    $organization = Organization::factory()->create();

    expect(MemberDeletion::enabled($organization))->toBeTrue()
        ->and(ContactDeletion::enabled($organization))->toBeTrue();
});

it('toggles member and contact deletion per tenant', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $component = Livewire::test(Features::class)
        ->assertSet('memberDeletion', true)
        ->assertSet('contactDeletion', true);

    $component->set('memberDeletion', false)->set('contactDeletion', false)->assertHasNoErrors();

    expect(MemberDeletion::enabled($organization))->toBeFalse()
        ->and(ContactDeletion::enabled($organization))->toBeFalse();

    $component->set('memberDeletion', true)->set('contactDeletion', true);

    expect(MemberDeletion::enabled($organization))->toBeTrue()
        ->and(ContactDeletion::enabled($organization))->toBeTrue();
});

it('toggles profile self-edit per tenant', function () {
    $organization = Organization::factory()->create();

    expect(ProfileSelfEdit::enabled($organization))->toBeFalse();

    actingAsMember($organization, Role::Owner);

    $component = Livewire::test(Features::class)
        ->assertSet('profileSelfEdit', false)
        ->set('profileSelfEdit', true)
        ->assertHasNoErrors();

    expect(ProfileSelfEdit::enabled($organization))->toBeTrue();

    $component->set('profileSelfEdit', false);

    expect(ProfileSelfEdit::enabled($organization))->toBeFalse();
});

it('forbids members from opening the features tab', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    Livewire::test(Features::class)->assertForbidden();
});

it('shows the features tab for every tenant', function () {
    $root = Organization::factory()->asRootTenant()->create();
    $tenant = Organization::factory()->asTenantOf($root)->create();

    actingAsMember($tenant, Role::Owner);

    get(route('org-settings.general'))->assertSee(__('Features'));
});

it('keeps the registration switch root only', function () {
    $root = Organization::factory()->asRootTenant()->create();
    $tenant = Organization::factory()->asTenantOf($root)->create();

    actingAsMember($tenant, Role::Owner);

    get(route('org-settings.features'))
        ->assertOk()
        ->assertSee(__('Gender field in contact'))
        ->assertDontSee(__('Public registration'));

    actingAsMember($root, Role::Owner);

    get(route('org-settings.features'))
        ->assertOk()
        ->assertSee(__('Gender field in contact'))
        ->assertSee(__('Public registration'));
});

it('forbids non-root managers from toggling registration', function () {
    $root = Organization::factory()->asRootTenant()->create();
    $tenant = Organization::factory()->asTenantOf($root)->create();

    actingAsMember($tenant, Role::Owner);

    Livewire::test(Features::class)
        ->set('registration', true)
        ->assertNotFound();

    expect(Registration::enabled())->toBeFalse();
});

it('rejects a stale page after the current organization changes', function () {
    $root = Organization::factory()->asRootTenant()->create();
    $tenantA = Organization::factory()->asTenantOf($root)->create();
    $tenantB = Organization::factory()->asTenantOf($root)->create();

    $user = User::factory()->withProfile()->create();
    app(CreateMember::class)->attach($tenantA, $user->person, isPrimary: true, role: Role::Owner);
    app(CreateMember::class)->attach($tenantB, $user->person, role: Role::Owner);

    actingAs($user);

    session([CurrentOrganization::SESSION_KEY => $tenantA->getKey()]);

    $component = Livewire::test(Features::class);

    session([CurrentOrganization::SESSION_KEY => $tenantB->getKey()]);

    $component
        ->set('contactGender', true)
        ->assertStatus(409);

    expect(ContactGender::enabled($tenantB))->toBeFalse();
});

it('is not available without an organization', function () {
    actingAs(User::factory()->withProfile()->create());

    get(route('org-settings.features'))->assertNotFound();
});

it('forbids plain members', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    Livewire::test(Features::class)->assertForbidden();
});
