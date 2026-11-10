<?php

declare(strict_types=1);

use App\Actions\Organizations\AddMember;
use App\Enums\Role;
use App\Events\MemberRoleChanged;
use App\Livewire\Members\Edit;
use App\Livewire\Members\Index;
use App\Livewire\OrganizationSwitcher;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\UserRoleChangedNotification;
use App\Notifications\VerifyEmailForNewUser;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests are redirected from the members page', function () {
    get(route('members.index'))->assertRedirect(route('login'));
});

test('the members page lists only the current organization members', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $inside = User::factory()->asMemberOf($organization)->create(['name' => 'Inside Member']);
    $outside = User::factory()->asMemberOf(Organization::factory())->create(['name' => 'Outside Member']);

    get(route('members.index'))
        ->assertOk()
        ->assertSee('Inside Member')
        ->assertDontSee('Outside Member');

    expect($inside->person)->not->toBeNull()
        ->and($outside->person)->not->toBeNull();
});

test('the members table shows role labels and verification status', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    User::factory()->asMemberOf($organization, Role::Admin)->create();
    User::factory()->unverified()->asMemberOf($organization, Role::Member)->create();

    Livewire::test(Index::class)
        ->assertSee('Admin')
        ->assertSee('Terverifikasi')
        ->assertSee('Menunggu');
});

test('members are paginated', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    User::factory()->count(14)->asMemberOf($organization)->create();

    Livewire::test(Index::class)
        ->assertViewHas('members', fn ($members) => $members->count() === 10 && $members->total() === 15)
        ->call('gotoPage', 2)
        ->assertViewHas('members', fn ($members) => $members->count() === 5);
});

test('a member can be viewed by uuid', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create(['name' => 'Viewable Member']);

    get(route('members.show', $member->person))
        ->assertOk()
        ->assertSee('Viewable Member');
});

test('a member from another organization is not found', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $outside = User::factory()->asMemberOf(Organization::factory())->create();

    get(route('members.show', $outside->person))->assertNotFound();
});

test('an admin can add a person without a credential', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Index::class)
        ->set('name', 'No Credential')
        ->set('role', Role::Member->value)
        ->set('createCredential', false)
        ->call('createMember')
        ->assertHasNoErrors();

    $person = Person::query()->where('name', 'No Credential')->firstOrFail();

    expect($person->user_id)->toBeNull()
        ->and($organization->members()->whereKey($person->getKey())->exists())->toBeTrue()
        ->and($person->roleIn($organization))->toBe(Role::Member);
});

test('an admin can add a member with an invite credential', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Index::class)
        ->set('name', 'Invited Member')
        ->set('email', 'invited@example.com')
        ->set('createCredential', true)
        ->set('role', Role::Member->value)
        ->call('createMember')
        ->assertHasNoErrors();

    $person = Person::query()->where('name', 'Invited Member')->firstOrFail();
    $user = $person->user;

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('invited@example.com')
        ->and($user->role)->toBe(Role::Member)
        ->and($user->email_verified_at)->toBeNull()
        ->and($person->roleIn($organization))->toBe(Role::Member);

    Notification::assertSentTo($user, VerifyEmailForNewUser::class);
});

test('an admin cannot assign the owner role', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Index::class)
        ->set('name', 'New Owner')
        ->set('role', Role::Owner->value)
        ->call('createMember')
        ->assertForbidden();

    expect(Person::query()->where('name', 'New Owner')->exists())->toBeFalse();
});

test('an owner can assign the owner role', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    Livewire::test(Index::class)
        ->set('name', 'New Owner')
        ->set('role', Role::Owner->value)
        ->call('createMember')
        ->assertHasNoErrors();

    $person = Person::query()->where('name', 'New Owner')->firstOrFail();

    expect($person->roleIn($organization))->toBe(Role::Owner);
});

test('a plain member cannot add members', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    Livewire::test(Index::class)
        ->set('name', 'Forbidden Member')
        ->set('role', Role::Member->value)
        ->call('createMember')
        ->assertForbidden();

    expect(Person::query()->where('name', 'Forbidden Member')->exists())->toBeFalse();
});

test('editing a member updates the person and the per-organization role', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    Livewire::test(Edit::class, ['person' => $member->person])
        ->set('name', 'Renamed Member')
        ->set('role', Role::Admin->value)
        ->call('update')
        ->assertHasNoErrors();

    expect($member->person->fresh()->name)->toBe('Renamed Member')
        ->and($member->person->roleIn($organization))->toBe(Role::Admin)
        ->and($member->fresh()->role)->toBe(Role::Member);
});

test('editing a member can create a credential', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $person = Person::factory()->withoutCredential()->create();
    app(AddMember::class)->handle($organization, $person, startDate: now());

    Livewire::test(Edit::class, ['person' => $person])
        ->set('createCredential', true)
        ->set('email', 'credential@example.com')
        ->call('update')
        ->assertHasNoErrors();

    $person->refresh();

    expect($person->user)->not->toBeNull()
        ->and($person->user->email)->toBe('credential@example.com');

    Notification::assertSentTo($person->user, VerifyEmailForNewUser::class);
});

test('removing a member keeps the person and credential', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();
    $person = $member->person;

    Livewire::test(Index::class)
        ->call('removeMember', $person->getKey())
        ->assertHasNoErrors();

    expect($organization->members()->whereKey($person->getKey())->exists())->toBeFalse()
        ->and($person->fresh())->not->toBeNull()
        ->and($person->fresh()->user)->not->toBeNull();
});

test('a plain member cannot remove another member', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    Livewire::test(Index::class)
        ->call('removeMember', $member->person->getKey())
        ->assertForbidden();

    expect($organization->members()->whereKey($member->person->getKey())->exists())->toBeTrue();
});

test('an admin cannot remove an owner', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();

    Livewire::test(Index::class)
        ->call('removeMember', $owner->person->getKey())
        ->assertForbidden();

    expect($organization->members()->whereKey($owner->person->getKey())->exists())->toBeTrue();
});

test('a user cannot remove their own membership', function () {
    $organization = Organization::factory()->create();

    $actor = actingAsMember($organization, Role::Owner);

    Livewire::test(Index::class)
        ->call('removeMember', $actor->person->getKey())
        ->assertForbidden();
});

test('an admin can remove selected members', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $members = User::factory()->count(3)->asMemberOf($organization)->create();

    Livewire::test(Index::class)
        ->set('selected', $members->map(fn (User $user) => $user->person->getKey())->all())
        ->call('deleteSelected')
        ->assertHasNoErrors();

    expect($organization->members()->whereKey($members->map(fn (User $user) => $user->person->getKey()))->count())->toBe(0)
        ->and(Person::query()->whereKey($members->map(fn (User $user) => $user->person->getKey()))->count())->toBe(3);
});

test('a role change promotes within the current organization only', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();
    app(AddMember::class)->handle($otherOrganization, $member->person, startDate: now(), role: Role::Member);

    Livewire::test(Index::class)
        ->call('confirmPromote', $member->person->getKey())
        ->call('changeRoleConfirmed')
        ->assertHasNoErrors();

    expect($member->person->roleIn($organization))->toBe(Role::Admin)
        ->and($member->person->roleIn($otherOrganization))->toBe(Role::Member);

    Notification::assertSentTo($member, UserRoleChangedNotification::class);
});

test('a role change dispatches the member role changed event', function () {
    Event::fake([MemberRoleChanged::class]);

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    Livewire::test(Index::class)
        ->call('confirmPromote', $member->person->getKey())
        ->call('changeRoleConfirmed');

    Event::assertDispatched(
        MemberRoleChanged::class,
        fn (MemberRoleChanged $event) => $event->organization->is($organization)
            && $event->person->is($member->person)
            && $event->previousRole === Role::Member
            && $event->newRole === Role::Admin,
    );
});

test('a plain member cannot change roles', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    Livewire::test(Index::class)
        ->call('confirmPromote', $member->person->getKey())
        ->call('changeRoleConfirmed')
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'danger');

    expect($member->person->roleIn($organization))->toBe(Role::Member);
});

test('the member detail page shows the membership role', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization, Role::Admin)->create(['name' => 'Detail Member']);

    get(route('members.show', $member->person))
        ->assertOk()
        ->assertSee('Detail Member')
        ->assertSee('Admin');
});

test('the edit member modal is a floating right-side flyout with a bottom footer', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $member = User::factory()->asMemberOf($organization)->create();

    Livewire::test(Edit::class, ['person' => $member->person])
        ->assertSeeHtml('data-flux-flyout')
        ->assertSeeHtml('ml-auto')
        ->assertSeeHtml('rounded-xl')
        ->assertSeeHtml('min-h-[calc(100dvh-5rem)]')
        ->assertSeeHtml('mt-auto');
});

test('the organization switcher lists active memberships and switches the session', function () {
    $first = Organization::factory()->create(['name' => 'First Org']);
    $second = Organization::factory()->create(['name' => 'Second Org']);

    $user = actingAsMember($first, Role::Member);
    app(AddMember::class)->handle($second, $user->person, startDate: now(), role: Role::Member);

    get(route('dashboard'))
        ->assertOk()
        ->assertSee('First Org')
        ->assertSee('Second Org');

    Livewire::test(OrganizationSwitcher::class)
        ->call('switchOrganization', $second->getKey())
        ->assertRedirect();

    expect(session(CurrentOrganization::SESSION_KEY))->toBe($second->getKey());
});

test('switching to an organization the user does not belong to is forbidden', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $outside = Organization::factory()->create();

    Livewire::test(OrganizationSwitcher::class)
        ->call('switchOrganization', $outside->getKey())
        ->assertForbidden();

    expect(session(CurrentOrganization::SESSION_KEY))->toBe($organization->getKey());
});

test('plain members do not see the organization settings item', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    get(route('dashboard'))->assertOk()->assertDontSee(__('Organization settings'));
});

test('owners and admins see the organization settings item', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    get(route('dashboard'))->assertOk()->assertSee(__('Organization settings'));
});

test('a user without an organization sees an empty members page', function () {
    $user = User::factory()->withProfile()->create();

    actingAs($user);

    get(route('members.index'))
        ->assertOk()
        ->assertSee(__('You are not a member of any organization.'));
});

test('the organization settings page shows the current organization', function () {
    $organization = Organization::factory()->create(['name' => 'Settings Org']);

    actingAsMember($organization, Role::Owner);

    get(route('org-settings'))
        ->assertOk()
        ->assertSee('Settings Org');
});

test('plain members cannot open the organization settings page', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    get(route('org-settings'))->assertForbidden();
});

test('the organization settings page is hidden from users without an organization', function () {
    $user = User::factory()->withProfile()->create();

    actingAs($user);

    get(route('org-settings'))->assertNotFound();
});
