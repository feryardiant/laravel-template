<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Livewire\OrgSettings\General;
use App\Models\AuditLog;
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

it('updates the organization summary', function () {
    $organization = Organization::factory()->create(['summary' => 'Old summary']);

    actingAsMember($organization, Role::Owner);

    Livewire::test(General::class)
        ->assertSet('summary', 'Old summary')
        ->set('summary', 'New summary')
        ->call('update')
        ->assertHasNoErrors();

    expect($organization->fresh()->summary)->toBe('New summary');
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

it('removes the organization logo', function () {
    Storage::fake();

    $organization = Organization::factory()->create(['logo_url' => 'logos/old.png']);
    Storage::disk()->put('logos/old.png', 'logo');

    actingAsMember($organization, Role::Owner);

    Livewire::test(General::class)
        ->call('clearLogo')
        ->call('update')
        ->assertHasNoErrors()
        ->assertSet('logo_url', null);

    expect($organization->fresh()->getRawOriginal('logo_url'))->toBeNull();
    expect(Storage::disk()->exists('logos/old.png'))->toBeFalse();
});

it('keeps the organization logo when removal fails', function () {
    Storage::fake();

    $organization = Organization::factory()->create(['logo_url' => 'logos/blocked']);

    // A directory at the object path cannot be unlinked, which forces the failure.
    Storage::disk()->makeDirectory('logos/blocked');

    actingAsMember($organization, Role::Owner);

    Livewire::test(General::class)
        ->call('clearLogo')
        ->call('update')
        ->assertSet('logo_url', 'logos/blocked')
        ->assertNotDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'success')
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'danger');

    expect($organization->fresh()->getRawOriginal('logo_url'))->toBe('logos/blocked');

    $logs = AuditLog::query()
        ->where('event', AuditEvent::OrganizationUpdated)
        ->orderBy('id')
        ->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->changes['fields'] ?? [])->toContain('logo_url')
        ->and($logs[1]->changes)->toMatchArray([
            'fields' => ['logo_url'],
            'restored' => true,
        ]);
});

it('renders the organization logo in the switcher', function () {
    $organization = Organization::factory()->create(['logo_url' => 'logos/logo.png']);

    actingAsMember($organization, Role::Owner);

    get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('uploads', ['path' => 'logos/logo.png']));
});

test('the danger zone is visible only to the owner', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);

    actingAsMember($organization, Role::Owner);

    get(route('org-settings.general'))
        ->assertOk()
        ->assertSee(__('Danger Zone'))
        ->assertSee(__('Transfer Ownership'))
        ->assertSee(__('Delete Organization'));

    actingAsMember($organization, Role::Admin);

    get(route('org-settings.general'))
        ->assertOk()
        ->assertDontSee(__('Danger Zone'));
});
