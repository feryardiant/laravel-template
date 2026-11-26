<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Actions\Memberships\RemoveMember;
use App\Enums\Role;
use App\Events\Memberships\MemberRemoved;
use App\Livewire\Members\Partials\RemoveMemberModal;
use App\Livewire\Members\Partials\RemoveMembersModal;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\MemberLeftNotification;
use App\Notifications\MembershipRemovedNotification;
use App\Support\Feature\MemberDeletion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use RuntimeException;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('removing a member keeps the person and credential', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();
    $person = $member->person;

    Livewire::test(RemoveMemberModal::class)
        ->call('confirm', $person->getKey())
        ->call('delete')
        ->assertHasNoErrors();

    expect($organization->members()->whereKey($person->getKey())->exists())->toBeFalse();
    expect($person->fresh())->not->toBeNull();
    expect($person->fresh()->user)->not->toBeNull();
});

test('a plain member cannot remove another member', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();

    Livewire::test(RemoveMemberModal::class)
        ->call('confirm', $member->person->getKey())
        ->call('delete')
        ->assertForbidden();

    expect($organization->members()->whereKey($member->person->getKey())->exists())->toBeTrue();
});

test('an admin cannot remove an owner', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();

    Livewire::test(RemoveMemberModal::class)
        ->call('confirm', $owner->person->getKey())
        ->call('delete')
        ->assertForbidden();

    expect($organization->members()->whereKey($owner->person->getKey())->exists())->toBeTrue();
});

test('a user cannot remove their own membership', function () {
    $organization = Organization::factory()->create();

    $actor = actingAsMember($organization, Role::Owner);

    Livewire::test(RemoveMemberModal::class)
        ->call('confirm', $actor->person->getKey())
        ->call('delete')
        ->assertForbidden();
});

test('an admin can remove selected members', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $members = User::factory()->count(3)->asMemberOf($organization)->create();

    Livewire::test(RemoveMembersModal::class)
        ->call('confirm', $members->map(fn (User $user) => $user->person->getKey())->all())
        ->call('delete')
        ->assertHasNoErrors();

    expect($organization->members()->whereKey($members->map(fn (User $user) => $user->person->getKey()))->count())->toBe(0);
    expect(Person::query()->whereKey($members->map(fn (User $user) => $user->person->getKey()))->count())->toBe(3);
});

test('removing a member notifies them once', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    $actor = actingAsMember($organization, Role::Owner);
    $member = User::factory()->asMemberOf($organization)->create();

    Livewire::test(RemoveMemberModal::class)
        ->call('confirm', $member->person->getKey())
        ->call('delete')
        ->assertHasNoErrors();

    Notification::assertSentToTimes($member, MembershipRemovedNotification::class, 1);
    Notification::assertSentTo(
        $member,
        MembershipRemovedNotification::class,
        fn (MembershipRemovedNotification $notification): bool => $notification->organization->is($organization)
            && $notification->actor?->is($actor) === true
            && $notification->lastMembership === true,
    );
});

test('removing the same member twice notifies only once', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    $actor = actingAsMember($organization, Role::Owner);
    $member = User::factory()->asMemberOf($organization)->create();

    $action = app(RemoveMember::class);
    $action->handle($organization, $member->person, $actor);
    $action->handle($organization, $member->person, $actor);

    Notification::assertSentToTimes($member, MembershipRemovedNotification::class, 1);
});

test('an actor demoted before the removal cannot remove a member', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    $actor = actingAsMember($organization, Role::Admin);
    $member = User::factory()->asMemberOf($organization)->create();

    $organization->members()->updateExistingPivot($actor->person->getKey(), ['role' => Role::Member]);

    app(RemoveMember::class)->handle($organization, $member->person, $actor);

    expect($organization->members()->whereKey($member->person->getKey())->exists())->toBeTrue();
    Notification::assertNothingSent();
});

test('a manager removal does not notify the owners', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    $actor = actingAsMember($organization, Role::Admin);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $member = User::factory()->asMemberOf($organization)->create();

    Livewire::test(RemoveMemberModal::class)
        ->call('confirm', $member->person->getKey())
        ->call('delete')
        ->assertHasNoErrors();

    Notification::assertNotSentTo($actor, MemberLeftNotification::class);
    Notification::assertNotSentTo($owner, MemberLeftNotification::class);
});

test('removing a person without a credential notifies nobody', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $person = Person::factory()->withoutCredential()->create();
    app(CreateMember::class)->attach($organization, $person);

    Livewire::test(RemoveMemberModal::class)
        ->call('confirm', $person->getKey())
        ->call('delete')
        ->assertHasNoErrors();

    expect($organization->members()->whereKey($person->getKey())->exists())->toBeFalse();
    Notification::assertNothingSent();
});

test('removing selected members notifies each credentialed member', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    $members = User::factory()->count(3)->asMemberOf($organization)->create();
    $credentialless = Person::factory()->withoutCredential()->create();
    app(CreateMember::class)->attach($organization, $credentialless);

    Livewire::test(RemoveMembersModal::class)
        ->call('confirm', $members->map(fn (User $user) => $user->person->getKey())->push($credentialless->getKey())->all())
        ->call('delete')
        ->assertHasNoErrors();

    $members->each(fn (User $member) => Notification::assertSentToTimes($member, MembershipRemovedNotification::class, 1));
    Notification::assertCount(3);
});

test('member removal is blocked when the workspace disables it', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();
    $other = User::factory()->asMemberOf($organization)->create();

    MemberDeletion::deactivate($organization);

    Livewire::test(RemoveMemberModal::class)
        ->call('confirm', $member->person->getKey())
        ->call('delete')
        ->assertForbidden();

    Livewire::test(RemoveMembersModal::class)
        ->call('confirm', [$other->person->getKey()])
        ->call('delete')
        ->assertForbidden();

    expect($organization->members()->whereKey($member->person->getKey())->exists())->toBeTrue()
        ->and($organization->members()->whereKey($other->person->getKey())->exists())->toBeTrue();

    Notification::assertNothingSent();
});

test('the member view hides removal controls when the workspace disables it', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);
    User::factory()->asMemberOf($organization)->create();

    MemberDeletion::deactivate($organization);

    get(route('members.index'))
        ->assertOk()
        ->assertDontSeeHtml('confirmDelete(')
        ->assertDontSeeHtml('confirmDeleteSelected')
        ->assertDontSee(__('Select all members'));
});

test('a plain member cannot bulk remove even with an empty selection', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    Livewire::test(RemoveMembersModal::class)
        ->call('confirm', [])
        ->call('delete')
        ->assertForbidden();
});

test('bulk removal is blocked when the workspace disables it even with an empty selection', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    MemberDeletion::deactivate($organization);

    Livewire::test(RemoveMembersModal::class)
        ->call('confirm', [])
        ->call('delete')
        ->assertForbidden();
});

test('the removal action refuses when the workspace disables it', function () {
    $organization = Organization::factory()->create();
    $owner = actingAsMember($organization, Role::Owner);
    $member = User::factory()->asMemberOf($organization)->create();

    MemberDeletion::deactivate($organization);

    app(RemoveMember::class)->handle($organization, $member->person, $owner);

    expect($organization->members()->whereKey($member->person->getKey())->exists())->toBeTrue();
});

test('a scheduled member can be removed', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $person = Person::factory()->withoutCredential()->create();
    app(CreateMember::class)->attach($organization, $person, startDate: now()->addDay());

    Livewire::test(RemoveMemberModal::class)
        ->call('confirm', $person->getKey())
        ->call('delete')
        ->assertHasNoErrors();

    expect($organization->members()->whereKey($person->getKey())->exists())->toBeFalse();
});

test('a removal rejected after the precheck shows a danger toast', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();

    $calls = 0;

    Gate::before(function (mixed $user, string $ability) use (&$calls): ?bool {
        if ($ability !== 'removeMember') {
            return null;
        }

        return ++$calls === 1;
    });

    Livewire::test(RemoveMemberModal::class)
        ->call('confirm', $member->person->getKey())
        ->call('delete')
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['dataset']['variant'] ?? null) === 'danger'
                && ($params['slots']['text'] ?? null) === __('Failed to remove the member.'),
        );

    expect($organization->members()->whereKey($member->person->getKey())->exists())->toBeTrue();
});

test('a failed bulk removal keeps the members and shows a danger toast', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();

    Event::listen(MemberRemoved::class, fn (): never => throw new RuntimeException('Removal failed.'));

    Livewire::test(RemoveMembersModal::class)
        ->call('confirm', [$member->person->getKey()])
        ->call('delete')
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['dataset']['variant'] ?? null) === 'danger'
                && ($params['slots']['text'] ?? null) === __('Failed to remove the selected members.'),
        );

    expect($organization->members()->whereKey($member->person->getKey())->exists())->toBeTrue();
});

test('bulk removal reports the detached count', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $members = User::factory()->count(2)->asMemberOf($organization)->create();

    Livewire::test(RemoveMembersModal::class)
        ->call('confirm', $members->map(fn (User $user): mixed => $user->person->getKey())->all())
        ->call('delete')
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['slots']['text'] ?? null) === __(':count members removed successfully.', ['count' => 2]),
        );
});
