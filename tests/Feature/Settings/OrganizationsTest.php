<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Actions\Organizations\CreateOrganization;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Jobs\DeleteOrganization;
use App\Livewire\Settings\Organizations;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\MemberLeftNotification;
use App\Notifications\MembershipLeaveRequestedNotification;
use App\Notifications\MembershipRemovedNotification;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('guests are redirected from the organizations settings page', function () {
    get(route('organizations.index'))->assertRedirect(route('login'));
});

test('the organizations settings page requires password confirmation', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    get(route('organizations.index'))->assertRedirect(route('password.confirm'));
});

test('the organizations settings page lists every joined organization with a role', function () {
    $first = Organization::factory()->create(['name' => 'First Org']);
    $second = Organization::factory()->create(['name' => 'Second Org']);

    $user = actingAsMember($first, Role::Admin);
    app(CreateMember::class)->attach($second, $user->person, startDate: now(), role: Role::Member);

    test()->withSession(['auth.password_confirmed_at' => time()]);

    get(route('organizations.index'))
        ->assertOk()
        ->assertSee('First Org')
        ->assertSee('Second Org')
        ->assertSee(__('Admin'))
        ->assertSee(__('Member'))
        ->assertSee(__('Current'))
        ->assertSee($first->created_at->timezone('UTC')->translatedFormat('d M Y'))
        ->assertSeeHtml('wire:click="manage(');
});

test('the default organization is marked and other rows can become default', function () {
    $first = Organization::factory()->create(['name' => 'First Org']);
    $second = Organization::factory()->create(['name' => 'Second Org']);

    $user = actingAsMember($first, Role::Member, true);
    app(CreateMember::class)->attach($second, $user->person, role: Role::Member);

    Livewire::test(Organizations::class)
        ->assertSee(__('Default'))
        ->assertSee(__('Set default'))
        ->assertSeeHtml('wire:click="setDefault('.$second->getKey().')"')
        ->assertDontSeeHtml('wire:click="setDefault('.$first->getKey().')"');
});

test('a member can change their default organization', function () {
    $first = Organization::factory()->create(['name' => 'First Org']);
    $second = Organization::factory()->create(['name' => 'Second Org']);

    $user = actingAsMember($first, Role::Member, true);
    app(CreateMember::class)->attach($second, $user->person, role: Role::Member);

    Livewire::test(Organizations::class)
        ->call('setDefault', $second->getKey())
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['slots']['text'] ?? null) === __('Default organization updated.'),
        );

    expect($user->person->fresh()->defaultOrganization()?->is($second))->toBeTrue()
        ->and($first->members()->whereKey($user->person->getKey())->firstOrFail()->pivot->is_primary)->toBeNull()
        ->and(session(CurrentOrganization::SESSION_KEY))->toBe($first->getKey());
});

test('a user cannot default an organization they do not belong to', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member, true);

    $foreign = Organization::factory()->create();

    Livewire::test(Organizations::class)
        ->call('setDefault', $foreign->getKey())
        ->assertNotFound();
});

test('a subsidiary row shows its parent organization', function () {
    Organization::factory()->asRootTenant()->create();

    $parent = Organization::factory()->create(['name' => 'Parent Org']);
    $user = actingAsMember($parent, Role::Owner);

    $child = app(CreateOrganization::class)->handle($user, 'Child Org', $parent);

    $component = Livewire::test(Organizations::class);

    $childRow = $component->instance()
        ->memberships()
        ->first(fn (array $membership): bool => $membership['organization']->is($child));

    expect($childRow['parent']?->is($parent))->toBeTrue()
        ->and($childRow['kind'])->toBe(SubsidiaryKind::Subsidiary);

    $component
        ->assertSee('Child Org')
        ->assertSee(__(':kind of :name', ['kind' => __('Subsidiary'), 'name' => 'Parent Org']));
});

test('the last owner can only remove the organization', function () {
    $organization = Organization::factory()->create();

    $user = actingAsMember($organization, Role::Owner);

    Livewire::test(Organizations::class)
        ->assertSeeHtml('wire:click="confirmRemove(')
        ->call('confirmLeave', $organization->getKey(), $organization->name)
        ->call('leave')
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['slots']['text'] ?? null) === __('You cannot leave while you are the last owner.'),
        );

    expect($organization->members()->whereKey($user->person->getKey())->exists())->toBeTrue();
    expect(session(CurrentOrganization::SESSION_KEY))->toBe($organization->getKey());
});

test('the last owner queues the organization removal after confirming its name', function () {
    Queue::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);

    $user = actingAsMember($organization, Role::Owner);

    Livewire::test(Organizations::class)
        ->call('confirmRemove', $organization->getKey())
        ->assertSet('showRemoveModal', true)
        ->set('confirmation', 'Acme')
        ->call('remove')
        ->assertHasNoErrors()
        ->assertDispatched(
            'toast-show',
            fn (string $name, array $params): bool => ($params['slots']['text'] ?? null) === __('Removal queued. :name will be deleted shortly.', ['name' => 'Acme']),
        );

    Queue::assertPushed(
        DeleteOrganization::class,
        fn (DeleteOrganization $job): bool => $job->organization->is($organization)
            && $job->actorId === $user->getKey(),
    );

    expect(session(CurrentOrganization::SESSION_KEY))->toBeNull();
});

test('the last owner can remove an organization that still has members', function () {
    Queue::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);

    actingAsMember($organization, Role::Owner);

    User::factory()->asMemberOf($organization, Role::Member)->create();

    Livewire::test(Organizations::class)
        ->call('confirmRemove', $organization->getKey())
        ->set('confirmation', 'Acme')
        ->call('remove')
        ->assertHasNoErrors();

    Queue::assertPushed(DeleteOrganization::class);
});

test('the last owner must type the exact organization name to remove it', function () {
    Queue::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);

    actingAsMember($organization, Role::Owner);

    Livewire::test(Organizations::class)
        ->call('confirmRemove', $organization->getKey())
        ->set('confirmation', 'acme')
        ->call('remove')
        ->assertHasErrors('confirmation');

    Queue::assertNothingPushed();
});

test('the root organization cannot be removed', function () {
    Queue::fake();

    $root = Organization::factory()->asRootTenant()->create();

    actingAsMember($root, Role::Owner);

    Livewire::test(Organizations::class)
        ->assertDontSeeHtml('wire:click="confirmRemove(')
        ->call('confirmRemove', $root->getKey())
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('an admin cannot remove an organization', function () {
    Queue::fake();

    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Admin);

    Livewire::test(Organizations::class)
        ->call('confirmRemove', $organization->getKey())
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('a non-member cannot remove an organization', function () {
    Queue::fake();

    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    Livewire::test(Organizations::class)
        ->call('confirmRemove', $other->getKey())
        ->assertNotFound();

    Queue::assertNothingPushed();
});

test('removing another organization keeps the current one', function () {
    Queue::fake();

    $current = Organization::factory()->create(['name' => 'Current Org']);
    $other = Organization::factory()->create(['name' => 'Other Org']);

    $user = actingAsMember($current, Role::Owner);

    app(CreateMember::class)->attach($other, $user->person, role: Role::Owner);

    Livewire::test(Organizations::class)
        ->call('confirmRemove', $other->getKey())
        ->set('confirmation', 'Other Org')
        ->call('remove')
        ->assertNoRedirect();

    Queue::assertPushed(
        DeleteOrganization::class,
        fn (DeleteOrganization $job): bool => $job->organization->is($other),
    );

    expect(session(CurrentOrganization::SESSION_KEY))->toBe($current->getKey());
});

test('a user without an organization sees the empty state', function () {
    actingAs(User::factory()->create());

    test()->withSession(['auth.password_confirmed_at' => time()]);

    get(route('organizations.index'))
        ->assertOk()
        ->assertSee(__('You are not a member of any organization.'))
        ->assertDontSee(__('You cannot leave while you are the last owner.'));
});

test('a member can leave the current organization', function () {
    $organization = Organization::factory()->create();

    $user = actingAsMember($organization, Role::Member);

    Livewire::test(Organizations::class)
        ->call('confirmLeave', $organization->getKey(), $organization->name)
        ->call('leave')
        ->assertRedirect(route('dashboard'));

    expect($organization->members()->whereKey($user->person->getKey())->exists())->toBeFalse();
    expect(session(CurrentOrganization::SESSION_KEY))->toBeNull();
});

test('an admin leave asks the owner for approval', function () {
    Notification::fake();

    $organization = Organization::factory()->create(['name' => 'Acme']);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = actingAsMember($organization, Role::Admin);

    Livewire::test(Organizations::class)
        ->call('confirmLeave', $organization->getKey(), $organization->name)
        ->assertSet('leaveNeedsApproval', true)
        ->call('leave')
        ->assertNoRedirect();

    expect($organization->members()->whereKey($admin->person->getKey())->exists())->toBeTrue()
        ->and(ApprovalRequest::query()->pending()->count())->toBe(1);

    Notification::assertSentTo($owner, MembershipLeaveRequestedNotification::class);
});

test('leaving another organization keeps the current one', function () {
    $current = Organization::factory()->create();
    $other = Organization::factory()->create();

    $user = actingAsMember($current, Role::Member);
    app(CreateMember::class)->attach($other, $user->person, startDate: now(), role: Role::Member);

    Livewire::test(Organizations::class)
        ->call('confirmLeave', $other->getKey(), $other->name)
        ->call('leave')
        ->assertNoRedirect();

    expect($other->members()->whereKey($user->person->getKey())->exists())->toBeFalse();
    expect(session(CurrentOrganization::SESSION_KEY))->toBe($current->getKey());
});

test('an owner can switch to another organization and open its settings', function () {
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();

    $user = actingAsMember($first, Role::Owner);
    app(CreateMember::class)->attach($second, $user->person, startDate: now(), role: Role::Admin);

    Livewire::test(Organizations::class)
        ->call('manage', $second->getKey())
        ->assertRedirect(route('org-settings.general'));

    expect(session(CurrentOrganization::SESSION_KEY))->toBe($second->getKey());
});

test('a member cannot open another organization settings', function () {
    $current = Organization::factory()->create();
    $other = Organization::factory()->create();

    $user = actingAsMember($current, Role::Member);
    app(CreateMember::class)->attach($other, $user->person, startDate: now(), role: Role::Member);

    Livewire::test(Organizations::class)
        ->call('manage', $other->getKey())
        ->assertForbidden();
});

test('leaving stores a confirmation notification when it was the last membership', function () {
    $organization = Organization::factory()->create();

    $user = actingAsMember($organization, Role::Member);

    Livewire::test(Organizations::class)
        ->call('confirmLeave', $organization->getKey(), $organization->name)
        ->call('leave');

    $notification = $user->notifications()->firstOrFail();

    expect($notification->type)->toBe(MembershipRemovedNotification::class);
    expect($notification->data['title'])->toBe(__('You left :organization', ['organization' => $organization->name]));
    expect($notification->data['lines'])->toContain(__('You are no longer a member of any organization.'));
});

test('leaving notifies the other owners in the database only', function () {
    Notification::fake();

    $organization = Organization::factory()->create();

    $leaver = actingAsMember($organization, Role::Member);
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $admin = User::factory()->asMemberOf($organization, Role::Admin)->create();

    Livewire::test(Organizations::class)
        ->call('confirmLeave', $organization->getKey(), $organization->name)
        ->call('leave');

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
