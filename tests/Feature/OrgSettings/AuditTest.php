<?php

declare(strict_types=1);

use App\Actions\Contacts\CreateContact;
use App\Actions\Memberships\ChangeMemberRole;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Livewire\OrgSettings\Audit;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function auditPageContactData(array $overrides = []): array
{
    return [
        'name' => 'Audit Contact',
        'summary' => null,
        'code' => 'person@example.com',
        'role' => OrganizationRelationshipType::Customer,
        'type' => 'individual',
        'start_date' => '2026-01-01',
        'finish_date' => null,
        'contact_name' => null,
        'contact_gender' => null,
        'contact_summary' => null,
        'reference' => null,
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $changes
 */
function auditEntryForPage(
    Organization $organization,
    AuditEvent $event,
    ?User $actor = null,
    ?Model $subject = null,
    array $changes = [],
    ?Carbon $createdAt = null,
): AuditLog {
    $log = AuditLog::create([
        'organization_id' => $organization->getKey(),
        'actor_id' => $actor?->getKey(),
        'event' => $event,
        'subject_type' => $subject?->getMorphClass(),
        'subject_id' => $subject?->getKey(),
        'changes' => $changes === [] ? null : $changes,
    ]);

    if ($createdAt !== null) {
        $log->forceFill(['created_at' => $createdAt])->saveQuietly();
    }

    return $log;
}

test('guests are redirected from the audit log page', function () {
    get(route('org-settings.audit'))->assertRedirect(route('login'));
});

test('plain members cannot open the audit log', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    get(route('org-settings.audit'))->assertForbidden();
});

test('owners and admins can open the audit log', function (Role $role) {
    $organization = Organization::factory()->create();

    actingAsMember($organization, $role);

    Livewire::test(Audit::class)
        ->assertOk()
        ->assertSee(__('No audit entries yet.'))
        ->assertSee(__('Date range'))
        ->assertSee('aria-label="'.__('Date range').'"', false);
})->with([Role::Owner, Role::Admin]);

test('the log only lists the current organization entries', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);

    $foreignOrganization = Organization::factory()->create();
    $foreignOwner = User::factory()->asMemberOf($foreignOrganization, Role::Owner)->create();

    auditEntryForPage($organization, AuditEvent::ContactCreated, $owner, $owner->person, [
        'kind' => 'individual',
        'type' => 'customer',
    ]);

    auditEntryForPage($foreignOrganization, AuditEvent::MemberLeft, $foreignOwner, $foreignOwner->person, [
        'via' => 'self',
    ]);

    $component = Livewire::test(Audit::class);

    expect($component->instance()->logs->pluck('event')->map->value->all())->toBe([
        AuditEvent::ContactCreated->value,
    ]);

    $component
        ->assertSee(__('Contact created'))
        ->assertSee($owner->person->name)
        ->assertDontSee($foreignOwner->person->name);
});

test('the event filter narrows the list and resets an invalid value', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);

    auditEntryForPage($organization, AuditEvent::ContactCreated, $owner, $owner->person);
    auditEntryForPage($organization, AuditEvent::MemberLeft, $owner, $owner->person, ['via' => 'self']);

    $component = Livewire::test(Audit::class)
        ->set('event', AuditEvent::ContactCreated->value)
        ->assertSee(__('Contact created'));

    expect($component->instance()->logs->pluck('event')->map->value->all())->toBe([
        AuditEvent::ContactCreated->value,
    ]);

    $component
        ->set('event', 'not-an-event')
        ->assertSet('event', null);

    expect($component->instance()->logs->total())->toBe(2);
});

test('the event and range filters compose', function () {
    /** @var TestCase $this */
    $this->travelTo('2026-11-18 12:00:00');

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);

    $contactInRange = auditEntryForPage(
        $organization,
        AuditEvent::ContactCreated,
        $owner,
        $owner->person,
        createdAt: Carbon::parse('2026-11-18 10:00'),
    );

    auditEntryForPage(
        $organization,
        AuditEvent::MemberLeft,
        $owner,
        $owner->person,
        ['via' => 'self'],
        Carbon::parse('2026-11-17 10:00'),
    );

    auditEntryForPage(
        $organization,
        AuditEvent::ContactCreated,
        $owner,
        $owner->person,
        createdAt: Carbon::parse('2026-11-04 10:00'),
    );

    $component = Livewire::test(Audit::class)
        ->set('event', AuditEvent::ContactCreated->value)
        ->set('range', ['start' => '2026-11-15', 'end' => '2026-11-18']);

    expect($component->instance()->logs->pluck('id')->all())->toBe([$contactInRange->getKey()]);
});

test('a reversed range is normalized', function () {
    /** @var TestCase $this */
    $this->travelTo('2026-11-18 12:00:00');

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    Livewire::test(Audit::class)
        ->set('range', ['start' => '2026-11-18', 'end' => '2026-11-16'])
        ->assertSet('range', ['start' => '2026-11-16', 'end' => '2026-11-18']);
});

test('the range end boundary uses the end of the user day', function () {
    /** @var TestCase $this */
    $this->travelTo('2026-11-18 12:00:00');

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $owner->update(['timezone' => 'Asia/Jakarta']);

    $inside = auditEntryForPage(
        $organization,
        AuditEvent::ContactCreated,
        $owner,
        $owner->person,
        createdAt: Carbon::parse('2026-11-18 16:59:59', 'UTC'),
    );

    auditEntryForPage(
        $organization,
        AuditEvent::ContactCreated,
        $owner,
        $owner->person,
        createdAt: Carbon::parse('2026-11-18 17:00:00', 'UTC'),
    );

    $component = Livewire::test(Audit::class)
        ->set('range', ['start' => '2026-11-18', 'end' => '2026-11-18']);

    expect($component->instance()->logs->pluck('id')->all())->toBe([$inside->getKey()]);
});

test('audit details translate field names and feature keys', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);

    auditEntryForPage($organization, AuditEvent::ContactUpdated, $owner, $owner->person, [
        'fields' => ['start_date', 'logo_url'],
    ]);

    auditEntryForPage($organization, AuditEvent::FeatureToggled, $owner, $organization, [
        'feature' => 'contact-gender',
        'enabled' => true,
    ]);

    auditEntryForPage($organization, AuditEvent::MemberRemoved, $owner, $owner->person, [
        'last_membership' => true,
    ]);

    Livewire::test(Audit::class)
        ->assertSee(__('Start date'))
        ->assertSee(__('Organization Logo'))
        ->assertSee(__('Gender field in contact'))
        ->assertSee(__('Last membership'));
});

test('a demoted manager cannot keep reading the audit log', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $admin = actingAsMember($organization, Role::Admin);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();

    $component = Livewire::test(Audit::class);

    app(ChangeMemberRole::class)->demote($organization, $admin->person, $owner);

    expect($admin->person->roleIn($organization))->toBe(Role::Member);

    $component
        ->set('event', AuditEvent::ContactCreated->value)
        ->assertForbidden();
});

test('contact audit rows render the relationship type without the code value', function () {
    $organization = Organization::factory()->create();
    $actor = actingAsMember($organization, Role::Admin);

    $entity = app(CreateContact::class)->handle($organization, auditPageContactData([
        'finish_date' => '2026-01-31',
    ]), actor: $actor);

    Livewire::test(Audit::class)
        ->assertSee(__('Contact created'))
        ->assertSee(OrganizationRelationshipType::Customer->label())
        ->assertDontSee('person@example.com');

    app(CreateContact::class)->handle($organization, auditPageContactData([
        'start_date' => '2026-03-01',
        'reference' => 'individual:'.$entity->uuid,
    ]), actor: $actor);

    Livewire::test(Audit::class)
        ->assertSee(__('Contact renewed'))
        ->assertSee(OrganizationRelationshipType::Customer->label())
        ->assertDontSee('person@example.com');
});

test('the default range covers this week', function () {
    /** @var TestCase $this */
    $this->travelTo('2026-11-18 12:00:00');

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    Livewire::test(Audit::class)
        ->assertSet('range', ['start' => '2026-11-15', 'end' => '2026-11-21']);
});

test('the range filter excludes entries outside the selected dates', function () {
    /** @var TestCase $this */
    $this->travelTo('2026-11-18 12:00:00');

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);

    $old = auditEntryForPage(
        $organization,
        AuditEvent::ContactCreated,
        $owner,
        $owner->person,
        createdAt: Carbon::parse('2026-11-04 12:00'),
    );

    $recent = auditEntryForPage(
        $organization,
        AuditEvent::ContactCreated,
        $owner,
        $owner->person,
        createdAt: Carbon::parse('2026-11-18 12:00'),
    );

    $component = Livewire::test(Audit::class);

    expect($component->instance()->logs->pluck('id')->all())->toBe([$recent->getKey()]);

    $component->set('range', ['start' => '2026-11-01', 'end' => '2026-11-18']);

    expect($component->instance()->logs->pluck('id')->all())->toBe([
        $recent->getKey(),
        $old->getKey(),
    ]);
});

test('an invalid range falls back to this week', function () {
    /** @var TestCase $this */
    $this->travelTo('2026-11-18 12:00:00');

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    Livewire::test(Audit::class)
        ->set('range', ['start' => 'not-a-date', 'end' => null])
        ->assertSet('range', ['start' => '2026-11-15', 'end' => '2026-11-21']);
});

test('the range filter uses the signed-in user timezone', function () {
    /** @var TestCase $this */
    $this->travelTo('2026-11-18 12:00:00');

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $owner->update(['timezone' => 'Asia/Jakarta']);

    auditEntryForPage(
        $organization,
        AuditEvent::ContactCreated,
        $owner,
        $owner->person,
        createdAt: Carbon::parse('2026-11-14 16:00:00', 'UTC'),
    );

    $boundary = auditEntryForPage(
        $organization,
        AuditEvent::ContactCreated,
        $owner,
        $owner->person,
        createdAt: Carbon::parse('2026-11-14 18:00:00', 'UTC'),
    );

    expect(Livewire::test(Audit::class)->instance()->logs->pluck('id')->all())
        ->toBe([$boundary->getKey()]);
});

test('the log is paginated newest first', function () {
    /** @var TestCase $this */
    $this->travelTo('2026-11-18 12:00:00');

    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);

    foreach (range(1, 21) as $minute) {
        auditEntryForPage(
            $organization,
            AuditEvent::ContactCreated,
            $owner,
            $owner->person,
            createdAt: Carbon::parse('2026-11-18 00:00')->addMinutes($minute),
        );
    }

    $component = Livewire::test(Audit::class);

    expect($component->instance()->logs->total())->toBe(21)
        ->and($component->instance()->logs->perPage())->toBe(20)
        ->and($component->instance()->logs->items()[0]->created_at->toDateString())->toBe('2026-11-18');

    $component->set('paginators.page', 2);
    $component->set('event', AuditEvent::ContactCreated->value);
    $component->assertSet('paginators.page', 1);

    $component->set('paginators.page', 2);
    $component->set('range', ['start' => '2026-11-15', 'end' => '2026-11-18']);
    $component->assertSet('paginators.page', 1);
});

test('a soft deleted subject still shows its name', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $person = Person::factory()->withoutCredential()->create(['name' => 'Archived Person']);

    $person->delete();

    auditEntryForPage($organization, AuditEvent::ContactDeleted, $owner, $person, ['detached_only' => false]);

    Livewire::test(Audit::class)->assertSee('Archived Person');
});

test('a hard deleted subject falls back to a deleted label', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $person = Person::factory()->withoutCredential()->create();

    $person->forceDelete();

    auditEntryForPage($organization, AuditEvent::ContactDeleted, $owner, $person);

    Livewire::test(Audit::class)->assertSee(__('Deleted :type', ['type' => __('Person')]));
});

test('entries without an actor show the system label', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    auditEntryForPage($organization, AuditEvent::MemberLeft, null, $organization);

    Livewire::test(Audit::class)
        ->assertSee(__('System'))
        ->assertSee(__('Member left'));
});
