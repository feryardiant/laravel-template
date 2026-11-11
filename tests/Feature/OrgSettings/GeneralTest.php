<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\OrgSettings\General;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('renders the current organization name', function () {
    $organization = Organization::factory()->create(['name' => 'Acme Org']);

    actingAsMember($organization, Role::Owner);

    Livewire::test(General::class)
        ->assertOk()
        ->assertSet('name', 'Acme Org');
});

it('updates the organization name', function () {
    $organization = Organization::factory()->create(['name' => 'Old Name']);

    actingAsMember($organization, Role::Owner);

    Livewire::test(General::class)
        ->set('name', 'New Name')
        ->call('update')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'success');

    expect($organization->fresh()->name)->toBe('New Name');
});

it('requires a name', function () {
    $organization = Organization::factory()->create(['name' => 'Old Name']);

    actingAsMember($organization, Role::Owner);

    Livewire::test(General::class)
        ->set('name', '')
        ->call('update')
        ->assertHasErrors(['name' => 'required']);

    expect($organization->fresh()->name)->toBe('Old Name');
});

it('allows admins to update the organization', function () {
    $organization = Organization::factory()->create(['name' => 'Old Name']);

    actingAsMember($organization, Role::Admin);

    Livewire::test(General::class)
        ->set('name', 'Admin Name')
        ->call('update')
        ->assertHasNoErrors();

    expect($organization->fresh()->name)->toBe('Admin Name');
});

it('forbids plain members', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    Livewire::test(General::class)->assertForbidden();

    get(route('org-settings.general'))->assertForbidden();
});

it('serves the page through the route', function () {
    $organization = Organization::factory()->create(['name' => 'Routed Org']);

    actingAsMember($organization, Role::Owner);

    get(route('org-settings.general'))
        ->assertOk()
        ->assertSee(__('Organization settings'));
});

it('returns not found without an organization', function () {
    $user = User::factory()->withProfile()->create();

    actingAs($user);

    get(route('org-settings.general'))->assertNotFound();
});

it('updates the organization logo', function () {
    Storage::fake(FileUploadConfiguration::disk());
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    Livewire::test(General::class)
        ->set('logo', UploadedFile::fake()->image('logo.png'))
        ->call('update')
        ->assertHasNoErrors();

    $organization->refresh();

    expect($organization->getRawOriginal('logo_url'))->toContain('logos/');
    expect(Storage::disk()->allFiles('logos'))->not->toBeEmpty();
});

it('renders the organization logo in the switcher', function () {
    $organization = Organization::factory()->create(['logo_url' => 'logos/logo.png']);

    actingAsMember($organization, Role::Owner);

    get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('uploads', ['path' => 'logos/logo.png']));
});
