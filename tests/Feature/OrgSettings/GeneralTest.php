<?php

declare(strict_types=1);

use App\Actions\Contacts\SaveContactDetails;
use App\Actions\Memberships\CreateMember;
use App\Actions\Organizations\Audit\OrganizationUpdatedPayload;
use App\Actions\Organizations\CreateOrganization;
use App\Enums\AuditEvent;
use App\Enums\ContactDetailType;
use App\Enums\Role;
use App\Livewire\OrgSettings\General;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
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
        ->assertSet('logoUrl', null);

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
        ->assertSet('logoUrl', 'logos/blocked')
        ->assertNotDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'success')
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'danger');

    expect($organization->fresh()->getRawOriginal('logo_url'))->toBe('logos/blocked');

    $logs = AuditLog::query()
        ->where('event', AuditEvent::OrganizationUpdated)
        ->orderBy('id')
        ->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->changes?->toArray())->toBe((new OrganizationUpdatedPayload(['logo_url']))->toArray())
        ->and($logs[1]->changes?->toArray())->toBe((new OrganizationUpdatedPayload(['logo_url'], true))->toArray());
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

it('saving the organization profile keeps its name and summary saved by another session', function () {
    $organization = Organization::factory()->create(['name' => 'Old Org', 'summary' => 'Old summary']);

    actingAsMember($organization, Role::Owner);

    $component = Livewire::test(General::class);

    $organization->update(['name' => 'Newer Org', 'summary' => 'Newer summary']);

    $component->set('contactPhone', '0899 444')->call('update')->assertHasNoErrors();

    $organization->refresh();

    expect($organization->name)->toBe('Newer Org')
        ->and($organization->summary)->toBe('Newer summary');
});

it('clearing the organization summary still clears the text', function () {
    $organization = Organization::factory()->create(['summary' => 'Old summary']);

    actingAsMember($organization, Role::Owner);

    Livewire::test(General::class)
        ->set('summary', '')
        ->call('update')
        ->assertHasNoErrors();

    expect((string) $organization->fresh()->summary)->toBe('');
});

it('saving the organization profile keeps a logo saved by another session', function () {
    Storage::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $component = Livewire::test(General::class);

    Storage::disk()->put('logos/new.png', 'image');
    $organization->update(['logo_url' => 'logos/new.png']);

    $component->set('summary', 'Updated summary')->call('update')->assertHasNoErrors();

    expect($organization->fresh()->getRawOriginal('logo_url'))->toBe('logos/new.png')
        ->and(Storage::disk()->exists('logos/new.png'))->toBeTrue();
});

it('saving the organization profile keeps an address saved by another session', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $component = Livewire::test(General::class);

    $organization->address()->create(validAddress());

    $component->set('summary', 'Updated summary')->call('update')->assertHasNoErrors();

    expect($organization->address()->exists())->toBeTrue();
});

it('saving the organization profile keeps contact details saved by another session', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $component = Livewire::test(General::class);

    app(SaveContactDetails::class)->handle($organization, [
        ContactDetailType::Email->value => 'newer@acme.test',
    ]);

    $component->set('summary', 'Updated summary')->call('update')->assertHasNoErrors();

    expect($organization->fresh()->contactDetailValue(ContactDetailType::Email))->toBe('newer@acme.test');
});

it('updates the organization contact details', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    Livewire::test(General::class)
        ->set('contactEmail', 'Hello@Acme.Test')
        ->set('contactPhone', '0812 3456')
        ->call('update')
        ->assertHasNoErrors();

    expect($organization->fresh()->contactDetailValue(ContactDetailType::Email))->toBe('hello@acme.test')
        ->and($organization->fresh()->contactDetailValue(ContactDetailType::Phone))->toBe('0812 3456');
});

it('a demoted manager cannot refresh the organization settings form', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);

    $component = Livewire::test(General::class);

    $organization->members()->updateExistingPivot($owner->person->getKey(), ['role' => Role::Member]);

    $component->call('$refresh')->assertForbidden();
});

it('shows owner navigation entries in organization settings', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $response = get(route('org-settings.features'))->assertOk();

    // The sidebar switcher and the nav item both link to General.
    expect(substr_count($response->getContent(), route('org-settings.general')))->toBe(2);

    $response
        ->assertSeeHtml(route('org-settings.features'))
        ->assertSeeHtml(route('org-settings.audit'))
        ->assertSeeHtml(route('org-settings.approval-history'))
        ->assertDontSeeHtml(route('org-settings.subsidiaries'));
});

it('hides owner-only navigation entries from admins', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $response = get(route('org-settings.features'))->assertOk();

    // The sidebar switcher and the nav item both link to General.
    expect(substr_count($response->getContent(), route('org-settings.general')))->toBe(2);

    $response
        ->assertSeeHtml(route('org-settings.features'))
        ->assertSeeHtml(route('org-settings.audit'))
        ->assertDontSeeHtml(route('org-settings.approval-history'))
        ->assertDontSeeHtml(route('org-settings.subsidiaries'));
});

it('shows the subsidiaries navigation on the root tenant only', function () {
    $root = Organization::factory()->asRootTenant()->create();

    actingAsMember($root, Role::Admin);

    get(route('org-settings.features'))
        ->assertOk()
        ->assertSeeHtml(route('org-settings.subsidiaries'))
        ->assertDontSeeHtml(route('org-settings.approval-history'));

    $tenant = Organization::factory()->create();

    actingAsMember($tenant, Role::Admin);

    get(route('org-settings.features'))
        ->assertOk()
        ->assertDontSeeHtml(route('org-settings.subsidiaries'));
});

it('hides the danger zone from a root owner who only administers the organization', function () {
    $root = Organization::factory()->asRootTenant()->create();
    $rootOwner = actingAsMember($root, Role::Owner);

    $childOwner = User::factory()->asMemberOf($root, Role::Member)->create();
    $child = app(CreateOrganization::class)->handle($childOwner, 'Child', $root);

    app(CreateMember::class)->attach($child, $rootOwner->person, startDate: now(), role: Role::Admin);

    test()->actingAs($rootOwner);
    session([CurrentOrganization::SESSION_KEY => $child->getKey()]);

    get(route('org-settings.general'))
        ->assertOk()
        ->assertDontSee(__('Danger Zone'))
        ->assertDontSeeHtml('confirmRemove');
});
