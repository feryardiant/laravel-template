<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Settings\Organizations;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\MemberLeftNotification;
use App\Notifications\MembershipRemovedNotification;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('guests are redirected from the organization settings page', function () {
    get(route('organization.edit'))->assertRedirect(route('login'));
});

test('the organization settings page requires password confirmation', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    get(route('organization.edit'))->assertRedirect(route('password.confirm'));
});

test('the organization settings page shows the current organization and role', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    test()->withSession(['auth.password_confirmed_at' => time()]);

    get(route('organization.edit'))
        ->assertOk()
        ->assertSee($organization->name)
        ->assertSee(__('Your role').': '.__('Admin'))
        ->assertSee(__('Leave organization'));
});

test('a user without an organization sees the empty state', function () {
    actingAs(User::factory()->create());

    test()->withSession(['auth.password_confirmed_at' => time()]);

    get(route('organization.edit'))
        ->assertOk()
        ->assertSee(__('You are not a member of any organization.'))
        ->assertDontSee(__('Leave organization'));
});

test('a member can leave the current organization', function () {
    $organization = Organization::factory()->create();

    $user = actingAsMember($organization, Role::Member);

    Livewire::test(Organizations::class)
        ->call('leave')
        ->assertRedirect(route('dashboard'));

    expect($organization->members()->whereKey($user->person->getKey())->exists())->toBeFalse();
    expect(session(CurrentOrganization::SESSION_KEY))->toBeNull();
});

test('leaving stores a confirmation notification when it was the last membership', function () {
    $organization = Organization::factory()->create();

    $user = actingAsMember($organization, Role::Member);

    Livewire::test(Organizations::class)->call('leave');

    $notification = $user->notifications()->firstOrFail();

    expect($notification->type)->toBe(MembershipRemovedNotification::class);
    expect($notification->data['title'])->toBe(__('You left :organization', ['organization' => $organization->name]));
    expect($notification->data['lines'])->toContain(__('You are no longer a member of any organization.'));
});

test('the last owner cannot leave the organization', function () {
    $organization = Organization::factory()->create();

    $user = actingAsMember($organization, Role::Owner);

    Livewire::test(Organizations::class)
        ->call('leave')
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['slots']['text'] ?? null) === __('You cannot leave while you are the last owner.'),
        );

    expect($organization->members()->whereKey($user->person->getKey())->exists())->toBeTrue();
    expect(session(CurrentOrganization::SESSION_KEY))->toBe($organization->getKey());
});

test('an owner can leave when another owner remains', function () {
    $organization = Organization::factory()->create();

    $user = actingAsMember($organization, Role::Owner);
    User::factory()->asMemberOf($organization, Role::Owner)->create();

    Livewire::test(Organizations::class)
        ->call('leave')
        ->assertRedirect(route('dashboard'));

    expect($organization->members()->whereKey($user->person->getKey())->exists())->toBeFalse();
});

test('leaving notifies the other owners in the database only', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    $leaver = actingAsMember($organization, Role::Member);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    Livewire::test(Organizations::class)->call('leave');

    Notification::assertSentTo(
        $owner,
        MemberLeftNotification::class,
        fn (MemberLeftNotification $notification): bool => $notification->organization->is($organization)
            && $notification->memberName === $leaver->name,
    );

    Notification::assertNotSentTo($leaver, MemberLeftNotification::class);
    Notification::assertNotSentTo($admin, MemberLeftNotification::class);
    expect((new MemberLeftNotification($organization, 'Someone'))->via($owner))->toBe(['database']);
});

test('leaving as an owner notifies the remaining owner', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    $leaver = actingAsMember($organization, Role::Owner);
    $otherOwner = User::factory()->asMemberOf($organization, Role::Owner)->create();

    Livewire::test(Organizations::class)->call('leave');

    Notification::assertSentTo($otherOwner, MemberLeftNotification::class);
    Notification::assertNotSentTo($leaver, MemberLeftNotification::class);
});

test('only active credentialed owners of the organization are notified', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $activeOwner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $expiredOwner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $futureOwner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $outsider = User::factory()->asMemberOf($otherOrganization, Role::Owner)->create();

    Person::factory()->withoutCredential()->asMemberOf($organization, Role::Owner)->create();

    $organization->members()->updateExistingPivot($expiredOwner->person->getKey(), ['finish_date' => now()->subDay()]);
    $organization->members()->updateExistingPivot($futureOwner->person->getKey(), ['start_date' => now()->addDay()]);

    Livewire::test(Organizations::class)->call('leave');

    Notification::assertSentToTimes($activeOwner, MemberLeftNotification::class, 1);
    Notification::assertNotSentTo(
        [$expiredOwner, $futureOwner, $outsider],
        MemberLeftNotification::class,
    );
});
