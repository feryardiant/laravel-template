<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Livewire\Dashboard;
use App\Models\Organization;
use App\Models\Person;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Attach an individual contact with an explicit date window.
 */
function dashboardContact(
    Organization $organization,
    string $name,
    ?string $start,
    ?string $finish = null,
    OrganizationRelationshipType $type = OrganizationRelationshipType::Customer,
): Person {
    $person = Person::factory()->withoutCredential()->create([
        'name' => $name,
        'owner_organization_id' => $organization->getKey(),
    ]);

    $organization->personCounterparties($type)->attach($person, [
        'type' => $type,
        'start_date' => $start,
        'finish_date' => $finish,
    ]);

    return $person;
}

beforeEach(function () {
    // Monday; the default range is Sunday 15th to Saturday 21st.
    $this->travelTo(CarbonImmutable::parse('2026-11-16 10:00:00'));
});

test('counts members and contacts that started inside the range', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $attach = app(CreateMember::class);

    $attach->attach($organization, Person::factory()->withoutCredential()->create(), startDate: CarbonImmutable::parse('2026-11-16'));
    $attach->attach($organization, Person::factory()->withoutCredential()->create(), startDate: CarbonImmutable::parse('2026-11-01'));
    $attach->attach($organization, Person::factory()->withoutCredential()->create(), startDate: CarbonImmutable::parse('2026-11-16'), finishDate: CarbonImmutable::parse('2026-11-10'));

    dashboardContact($organization, 'Inside Contact', '2026-11-18');
    dashboardContact($organization, 'Old Contact', '2026-10-30');

    $component = Livewire::test(Dashboard::class)->instance();

    expect($component->newMembers)->toBe(2)
        ->and($component->newContacts)->toBe(1);
});

test('lists only unexpired external contacts finishing inside the range', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    dashboardContact($organization, 'Ending Soon', '2026-11-01', '2026-11-18');
    dashboardContact($organization, 'Already Expired', '2026-11-01', '2026-11-10');
    dashboardContact($organization, 'Next Month', '2026-11-01', '2026-12-15');
    dashboardContact($organization, 'Not Started Yet', '2026-11-20', '2026-11-18');

    app(CreateMember::class)->attach(
        $organization,
        Person::factory()->withoutCredential()->create(),
        startDate: CarbonImmutable::parse('2026-11-01'),
        finishDate: CarbonImmutable::parse('2026-11-18'),
    );

    $component = Livewire::test(Dashboard::class)->instance();

    expect($component->expiringCount)->toBe(1)
        ->and($component->expiringContacts->pluck('stakeholder.name')->all())->toBe(['Ending Soon']);
});

test('the range filters the counts and the recent member list', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $attach = app(CreateMember::class);

    $attach->attach($organization, Person::factory()->withoutCredential()->create(['name' => 'This Week']), startDate: CarbonImmutable::parse('2026-11-17'));
    $attach->attach($organization, Person::factory()->withoutCredential()->create(['name' => 'Earlier']), startDate: CarbonImmutable::parse('2026-11-03'));

    $component = Livewire::test(Dashboard::class)
        ->assertSet('range.start', '2026-11-15')
        ->assertSet('range.end', '2026-11-21')
        ->set('range', ['start' => '2026-11-01', 'end' => '2026-11-30'])
        ->instance();

    expect($component->newMembers)->toBe(3)
        ->and($component->recentMembers->pluck('name')->first())->toBe('This Week')
        ->and($component->recentMembers->pluck('name')->last())->toBe('Earlier');
});

test('lists a soft-deleted contact with its name instead of a broken link', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $person = dashboardContact($organization, 'Deleted Contact', '2026-11-01', '2026-11-18');
    $person->delete();

    // `FindContact::byUuid()` does not resolve trashed people, so no link.
    Livewire::test(Dashboard::class)
        ->assertSee('Deleted Contact')
        ->assertDontSeeHtml(route('contacts.view', ['stakeholder' => $person]));
});

test('counts only unread notifications created inside the range', function () {
    $organization = Organization::factory()->create();

    $owner = actingAsMember($organization, Role::Owner);

    $notify = fn (string $createdAt, bool $read = false) => $owner->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\DashboardTestNotification',
        'data' => ['title' => 'Hello'],
        'read_at' => $read ? $createdAt : null,
        'created_at' => $createdAt,
    ]);

    $notify('2026-11-16 08:00:00');
    $notify('2026-11-16 09:00:00', read: true);
    $notify('2026-10-01 09:00:00');

    expect(Livewire::test(Dashboard::class)->instance()->unreadNotifications)->toBe(1);
});
