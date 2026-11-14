<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Jobs\DeleteOrganization;
use App\Livewire\OrgSettings\Subsidiaries;
use App\Livewire\Settings\Organizations\Create;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->root = Organization::factory()->asRootTenant()->create(['name' => 'Root Org']);
});

function makeInternalSubsidiary(
    Organization $parent,
    Organization $root,
    string $name,
    ?SubsidiaryKind $kind = null,
): Organization {
    $organization = Organization::factory()->asTenantOf($root)->create(['name' => $name]);

    $parent->subsidiaries()->attach($organization, [
        'type' => OrganizationRelationshipType::Subsidiary,
        'kind' => $kind ?? SubsidiaryKind::Subsidiary,
    ]);

    return $organization;
}

test('guests are redirected from the subsidiaries page', function () {
    get(route('org-settings.subsidiaries'))->assertRedirect(route('login'));
});

test('the subsidiaries page is not found outside the root tenant', function () {
    $tenant = Organization::factory()->create();

    actingAsMember($tenant, Role::Owner);

    get(route('org-settings.subsidiaries'))->assertNotFound();
});

test('root members cannot access the subsidiaries page', function () {
    actingAsMember($this->root, Role::Member);

    get(route('org-settings.subsidiaries'))->assertForbidden();
});

test('root admins can view the list but cannot manage it', function () {
    actingAsMember($this->root, Role::Admin);

    makeInternalSubsidiary($this->root, $this->root, 'Alpha');

    get(route('org-settings.subsidiaries'))
        ->assertOk()
        ->assertSee('Alpha')
        ->assertDontSeeHtml('data-test="new-subsidiary"')
        ->assertDontSeeHtml('confirmRemove(');
});

test('root owners can view and manage the list', function () {
    actingAsMember($this->root, Role::Owner);

    makeInternalSubsidiary($this->root, $this->root, 'Alpha');

    get(route('org-settings.subsidiaries'))
        ->assertOk()
        ->assertSee('Alpha')
        ->assertSeeHtml('data-test="new-subsidiary"')
        ->assertSeeHtml('confirmRemove(');
});

test('the list shows the internal subsidiary tree only', function () {
    actingAsMember($this->root, Role::Owner);

    $alpha = makeInternalSubsidiary($this->root, $this->root, 'Alpha');
    $beta = makeInternalSubsidiary($alpha, $this->root, 'Beta', SubsidiaryKind::Branch);

    $customer = Organization::factory()->asTenantOf($this->root)->create(['name' => 'Customer Org']);
    $this->root->organizationCounterparties(OrganizationRelationshipType::Customer)->attach($customer, [
        'type' => OrganizationRelationshipType::Customer,
    ]);
    $customerChild = Organization::factory()->asTenantOf($this->root)->create(['name' => 'Customer Child']);
    $customer->subsidiaries()->attach($customerChild, ['type' => OrganizationRelationshipType::Subsidiary]);

    Livewire::test(Subsidiaries::class)
        ->assertSee('Alpha')
        ->assertSee('Beta')
        ->assertDontSee('Customer Org')
        ->assertDontSee('Customer Child')
        ->assertSee($beta->created_at->timezone('UTC')->translatedFormat('d M Y'))
        ->assertSee(__(':kind of :name', ['kind' => __('Branch'), 'name' => 'Alpha']));
});

test('an owner creates a direct subsidiary under the root', function () {
    $owner = actingAsMember($this->root, Role::Owner);

    Livewire::test(Create::class, ['internal' => true])
        ->call('open')
        ->assertSet('showModal', true)
        ->set('name', 'Support')
        ->set('kind', SubsidiaryKind::Division->value)
        ->call('create')
        ->assertHasNoErrors()
        ->assertDispatched('organization-created');

    $organization = Organization::query()->where('name', 'Support')->firstOrFail();

    $relationship = OrganizationRelationship::query()
        ->whereStakeholder($organization)
        ->where('type', OrganizationRelationshipType::Subsidiary)
        ->firstOrFail();

    expect($organization->is_tenant)->toBeTrue()
        ->and($organization->root_organization_id)->toBe($this->root->getKey())
        ->and($relationship->organization_id)->toBe($this->root->getKey())
        ->and($relationship->kind)->toBe(SubsidiaryKind::Division)
        ->and($owner->roleIn($organization))->toBe(Role::Owner)
        ->and(session(CurrentOrganization::SESSION_KEY))->toBe($this->root->getKey());
});

test('an owner creates a nested subsidiary under an existing subsidiary', function () {
    actingAsMember($this->root, Role::Owner);

    $alpha = makeInternalSubsidiary($this->root, $this->root, 'Alpha');

    Livewire::test(Create::class, ['internal' => true])
        ->set('name', 'Beta')
        ->set('parent_id', (string) $alpha->getKey())
        ->call('create')
        ->assertHasNoErrors();

    $beta = Organization::query()->where('name', 'Beta')->firstOrFail();

    $relationship = OrganizationRelationship::query()
        ->whereStakeholder($beta)
        ->where('type', OrganizationRelationshipType::Subsidiary)
        ->firstOrFail();

    expect($relationship->organization_id)->toBe($alpha->getKey())
        ->and($beta->root_organization_id)->toBe($this->root->getKey());
});

test('the parent field is hidden when there are no subsidiaries', function () {
    actingAsMember($this->root, Role::Owner);

    Livewire::test(Create::class, ['internal' => true])
        ->assertDontSee(__('Parent organization'));
});

test('the parent field lists existing subsidiaries', function () {
    actingAsMember($this->root, Role::Owner);

    makeInternalSubsidiary($this->root, $this->root, 'Alpha');

    Livewire::test(Create::class, ['internal' => true])
        ->assertSee(__('Parent organization'))
        ->assertSee('Alpha');
});

test('a root admin cannot open or submit the subsidiary form', function () {
    actingAsMember($this->root, Role::Admin);

    Livewire::test(Create::class, ['internal' => true])
        ->call('open')
        ->assertForbidden();

    Livewire::test(Create::class, ['internal' => true])
        ->set('name', 'Nope')
        ->call('create')
        ->assertForbidden();

    expect(Organization::query()->where('name', 'Nope')->exists())->toBeFalse();
});

test('an owner queues the removal of a subsidiary and its nested subsidiaries', function () {
    Queue::fake();

    $owner = actingAsMember($this->root, Role::Owner);

    $alpha = makeInternalSubsidiary($this->root, $this->root, 'Alpha');
    makeInternalSubsidiary($alpha, $this->root, 'Beta');

    Livewire::test(Subsidiaries::class)
        ->call('confirmRemove', $alpha->getKey())
        ->assertSet('removingCount', 2)
        ->call('remove')
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['slots']['text'] ?? null) === __('Removal queued. :name will be deleted shortly.', ['name' => 'Alpha']),
        );

    Queue::assertPushed(
        DeleteOrganization::class,
        fn (DeleteOrganization $job): bool => $job->organization->is($alpha)
            && $job->actorId === $owner->getKey(),
    );
});

test('a root admin cannot queue a removal', function () {
    Queue::fake();

    actingAsMember($this->root, Role::Admin);

    $alpha = makeInternalSubsidiary($this->root, $this->root, 'Alpha');

    Livewire::test(Subsidiaries::class)
        ->call('confirmRemove', $alpha->getKey())
        ->assertForbidden();

    Livewire::test(Subsidiaries::class)
        ->call('remove')
        ->assertForbidden();

    Queue::assertNothingPushed();
});
