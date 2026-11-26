<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Enums\AuditEvent;
use App\Enums\Feature;
use App\Enums\Role;
use App\Livewire\OrgSettings\Features;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

dataset('features', array_reduce(Feature::cases(), function ($cases, Feature $feature) {
    $cases[$feature->name] = [$feature, Str::camel($feature->value)];

    return $cases;
}, []));

it('toggles registration for the root tenant', function () {
    $root = Organization::factory()->asRootTenant()->create();

    actingAsMember($root, Role::Owner);

    Livewire::test(Features::class)
        ->assertSet('isRootTenant', true)
        ->assertSet('registration', false)
        ->set('registration', true)
        ->assertHasNoErrors();

    expect(Feature::Registration->enabled())->toBeTrue();

    Livewire::test(Features::class)
        ->assertSet('registration', true)
        ->set('registration', false)
        ->assertHasNoErrors();

    expect(Feature::Registration->enabled())->toBeFalse();
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

    expect(Feature::ContactGender->enabled($tenant))->toBeTrue();

    Livewire::test(Features::class)
        ->assertSet('contactGender', true)
        ->set('contactGender', false)
        ->assertHasNoErrors();

    expect(Feature::ContactGender->enabled($tenant))->toBeFalse();
});

it('enables member and contact deletion by default', function () {
    $organization = Organization::factory()->create();

    expect(Feature::MemberDeletion->enabled($organization))->toBeTrue()
        ->and(Feature::ContactDeletion->enabled($organization))->toBeTrue();
});

it('toggles member and contact deletion per tenant', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $component = Livewire::test(Features::class)
        ->assertSet('memberDeletion', true)
        ->assertSet('contactDeletion', true);

    $component->set('memberDeletion', false)->set('contactDeletion', false)->assertHasNoErrors();

    expect(Feature::MemberDeletion->enabled($organization))->toBeFalse()
        ->and(Feature::ContactDeletion->enabled($organization))->toBeFalse();

    $component->set('memberDeletion', true)->set('contactDeletion', true);

    expect(Feature::MemberDeletion->enabled($organization))->toBeTrue()
        ->and(Feature::ContactDeletion->enabled($organization))->toBeTrue();
});

it('toggles profile self-edit per tenant', function () {
    $organization = Organization::factory()->create();

    expect(Feature::ProfileSelfEdit->enabled($organization))->toBeFalse();

    actingAsMember($organization, Role::Owner);

    $component = Livewire::test(Features::class)
        ->assertSet('profileSelfEdit', false)
        ->set('profileSelfEdit', true)
        ->assertHasNoErrors();

    expect(Feature::ProfileSelfEdit->enabled($organization))->toBeTrue();

    $component->set('profileSelfEdit', false);

    expect(Feature::ProfileSelfEdit->enabled($organization))->toBeFalse();
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

    expect(Feature::Registration->enabled())->toBeFalse();
});

it('rejects a stale page after the current organization changes', function (Feature $feature, string $field) {
    $root = Organization::factory()->asRootTenant()->create();
    $tenantA = Organization::factory()->asTenantOf($root)->create();
    $tenantB = Organization::factory()->asTenantOf($root)->create();

    $user = User::factory()->withProfile()->create();
    app(CreateMember::class)->attach($tenantA, $user->person, isPrimary: true, role: Role::Owner);
    app(CreateMember::class)->attach($tenantB, $user->person, role: Role::Owner);

    actingAs($user);

    session([CurrentOrganization::SESSION_KEY => $tenantA->getKey()]);

    $page = Livewire::test(Features::class);

    session([CurrentOrganization::SESSION_KEY => $tenantB->getKey()]);

    $page
        ->set($field, ! $feature->default())
        ->assertStatus(409);

    expect($feature->enabled($tenantB))->toBe($feature->default());
})->with('features');

it('is not available without an organization', function () {
    actingAs(User::factory()->withProfile()->create());

    get(route('org-settings.features'))->assertNotFound();
});

it('forbids plain members', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    Livewire::test(Features::class)->assertForbidden();
});

it('does not audit a feature toggle that changes nothing', function (Feature $feature, string $field) {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $component = Livewire::test(Features::class);

    $component->set($field, $feature->default());

    expect(AuditLog::query()->where('event', AuditEvent::FeatureToggled)->count())->toBe(0);
})->with('features');
