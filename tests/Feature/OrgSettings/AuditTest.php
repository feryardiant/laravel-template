<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Livewire\OrgSettings\Audit;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

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
        ->assertSee(__('No audit entries yet.'));
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

test('the log is paginated newest first', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);

    foreach (range(1, 21) as $day) {
        auditEntryForPage(
            $organization,
            AuditEvent::ContactCreated,
            $owner,
            $owner->person,
            createdAt: Carbon::parse('2026-01-01 00:00')->addDays($day),
        );
    }

    $component = Livewire::test(Audit::class);

    expect($component->instance()->logs->total())->toBe(21)
        ->and($component->instance()->logs->perPage())->toBe(20)
        ->and($component->instance()->logs->items()[0]->created_at->toDateString())->toBe('2026-01-22');
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
