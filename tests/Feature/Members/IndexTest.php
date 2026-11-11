<?php

declare(strict_types=1);

use App\Actions\Organizations\AddMember;
use App\Enums\Role;
use App\Events\MemberRoleChanged;
use App\Livewire\Members\Index;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\UserRoleChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('guests are redirected from the members page', function () {
    get(route('members.index'))->assertRedirect(route('login'));
});

test('a user without an organization sees an empty members page', function () {
    $user = User::factory()->withProfile()->create();

    actingAs($user);

    get(route('members.index'))
        ->assertOk()
        ->assertSee(__('You are not a member of any organization.'));
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

    expect($inside->person)->not->toBeNull();
    expect($outside->person)->not->toBeNull();
});

test('the members table shows role labels and verification status', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    User::factory()->asMemberOf($organization, Role::Admin)->create();
    User::factory()->unverified()->asMemberOf($organization, Role::Member)->create();

    Livewire::test(Index::class)
        ->assertSee(__('Admin'))
        ->assertSee(__('Verified'))
        ->assertSee(__('Waiting'));
});

test('member photos render in the member list', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create();
    $member->person->update(['photo_url' => 'photos/member.jpg']);

    get(route('members.index'))
        ->assertOk()
        ->assertSee(route('uploads', ['path' => 'photos/member.jpg']));
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

    expect($member->person->roleIn($organization))->toBe(Role::Admin);
    expect($member->person->roleIn($otherOrganization))->toBe(Role::Member);

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
