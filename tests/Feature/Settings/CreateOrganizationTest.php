<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Livewire\Settings\Organizations;
use App\Livewire\Settings\Organizations\Create;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->root = Organization::factory()->asRootTenant()->create(['name' => 'Root']);
});

test('a verified user creates a tenant they own', function () {
    $user = User::factory()->withProfile()->create();

    test()->actingAs($user);

    Livewire::test(Create::class)
        ->call('open')
        ->assertSet('showModal', true)
        ->set('name', 'Acme')
        ->call('create')
        ->assertHasNoErrors()
        ->assertDispatched('organization-created')
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['slots']['text'] ?? null) === __(':name has been created.', ['name' => 'Acme'])
                && ($params['link']['label'] ?? null) === __('Open organization settings')
                && ($params['link']['href'] ?? null) === route('org-settings.general'),
        );

    $organization = Organization::query()->where('name', 'Acme')->firstOrFail();

    expect($organization->is_tenant)->toBeTrue()
        ->and($organization->root_organization_id)->toBe($this->root->getKey())
        ->and($organization->contact_id)->toBe($user->person->getKey())
        ->and($user->roleIn($organization))->toBe(Role::Owner)
        ->and(session(CurrentOrganization::SESSION_KEY))->toBe($organization->getKey());
});

test('selecting an owned parent links the new organization as a subsidiary', function () {
    $parent = Organization::factory()->create();

    $user = actingAsMember($parent, Role::Owner);

    Livewire::test(Create::class)
        ->set('name', 'Child Org')
        ->set('parent_id', (string) $parent->getKey())
        ->call('create')
        ->assertHasNoErrors();

    $child = Organization::query()->where('name', 'Child Org')->firstOrFail();

    $relationship = OrganizationRelationship::query()
        ->whereStakeholder($child)
        ->where('type', OrganizationRelationshipType::Subsidiary)
        ->firstOrFail();

    expect($child->is_tenant)->toBeTrue()
        ->and($child->root_organization_id)->toBe($this->root->getKey())
        ->and($parent->subsidiaries()->whereKey($child->getKey())->exists())->toBeTrue()
        ->and($relationship->kind)->toBe(SubsidiaryKind::Subsidiary)
        ->and($user->roleIn($child))->toBe(Role::Owner);
});

test('a branch kind is stored and shown on the organization list', function () {
    $parent = Organization::factory()->create(['name' => 'Parent Org']);

    $user = actingAsMember($parent, Role::Owner);

    Livewire::test(Create::class)
        ->set('name', 'Branch Org')
        ->set('parent_id', (string) $parent->getKey())
        ->set('kind', SubsidiaryKind::Branch->value)
        ->call('create')
        ->assertHasNoErrors();

    $child = Organization::query()->where('name', 'Branch Org')->firstOrFail();

    $relationship = OrganizationRelationship::query()
        ->whereStakeholder($child)
        ->where('type', OrganizationRelationshipType::Subsidiary)
        ->firstOrFail();

    expect($relationship->kind)->toBe(SubsidiaryKind::Branch);

    Livewire::test(Organizations::class)
        ->assertSee(__(':kind of :name', ['kind' => __('Branch'), 'name' => 'Parent Org']));
});

test('an unknown subsidiary kind is rejected', function () {
    $parent = Organization::factory()->create();

    actingAsMember($parent, Role::Owner);

    Livewire::test(Create::class)
        ->set('name', 'Not Created')
        ->set('parent_id', (string) $parent->getKey())
        ->set('kind', 'franchise')
        ->call('create')
        ->assertHasErrors('kind');

    expect(Organization::query()->where('name', 'Not Created')->exists())->toBeFalse();
});

test('a parent the user does not own cannot be selected', function () {
    $owned = Organization::factory()->create();
    $foreign = Organization::factory()->create();

    $user = actingAsMember($owned, Role::Owner);

    app(CreateMember::class)->attach($foreign, $user->person, startDate: now(), role: Role::Member);

    Livewire::test(Create::class)
        ->set('name', 'Not Created')
        ->set('parent_id', (string) $foreign->getKey())
        ->call('create')
        ->assertHasErrors('parent_id');

    expect(Organization::query()->where('name', 'Not Created')->exists())->toBeFalse();
});

test('the parent field is hidden when the user owns no organization', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    test()->withSession(['auth.password_confirmed_at' => time()]);

    get(route('organizations.index'))
        ->assertOk()
        ->assertSee(__('New organization'))
        ->assertDontSee(__('Parent organization'));
});

test('the creator becomes the primary member only when they have no primary organization', function () {
    $user = User::factory()->withProfile()->create();

    test()->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'First Org')
        ->call('create')
        ->assertHasNoErrors();

    $first = Organization::query()->where('name', 'First Org')->firstOrFail();

    expect($first->members()->whereKey($user->person->getKey())->firstOrFail()->pivot->is_primary)->toBeTrue();

    Livewire::test(Create::class)
        ->set('name', 'Second Org')
        ->call('create')
        ->assertHasNoErrors();

    $second = Organization::query()->where('name', 'Second Org')->firstOrFail();

    expect($second->members()->whereKey($user->person->getKey())->firstOrFail()->pivot->is_primary)->toBeNull();
});

test('a logo can be uploaded with the new organization', function () {
    Storage::fake(FileUploadConfiguration::disk());
    Storage::fake();

    $user = User::factory()->withProfile()->create();

    test()->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'Logo Org')
        ->set('logo', UploadedFile::fake()->image('logo.png'))
        ->call('create')
        ->assertHasNoErrors();

    $organization = Organization::query()->where('name', 'Logo Org')->firstOrFail();

    expect($organization->getRawOriginal('logo_url'))->toContain('logos/');
    expect(Storage::disk()->allFiles('logos'))->not->toBeEmpty();
});
