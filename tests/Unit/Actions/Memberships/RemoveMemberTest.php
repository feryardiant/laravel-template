<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Actions\Memberships\RemoveMember;
use App\Actions\Tenancies\ToggleFeature;
use App\Enums\Feature;
use App\Enums\Role;
use App\Events\Memberships\MemberRemoved;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('removes memberships and flags whether each was the last one', function () {
    Event::fake([MemberRemoved::class]);

    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $lastMember = User::factory()->asMemberOf($organization)->create();
    $sharedMember = User::factory()->asMemberOf($organization)->create();

    app(CreateMember::class)->attach($otherOrganization, $sharedMember->person);

    $removed = app(RemoveMember::class)->handleMany(
        $organization,
        new Collection([$lastMember->person, $sharedMember->person]),
    );

    expect($removed)->toHaveCount(2)
        ->and($organization->members()->count())->toBe(0);

    Event::assertDispatched(
        MemberRemoved::class,
        fn (MemberRemoved $event): bool => $event->person->is($lastMember->person) && $event->lastMembership,
    );

    Event::assertDispatched(
        MemberRemoved::class,
        fn (MemberRemoved $event): bool => $event->person->is($sharedMember->person) && ! $event->lastMembership,
    );
});

it('does nothing when there is nobody to remove', function () {
    Event::fake([MemberRemoved::class]);

    $removed = app(RemoveMember::class)->handleMany(Organization::factory()->create(), new Collection);

    expect($removed)->toBeEmpty();

    Event::assertNotDispatched(MemberRemoved::class);
});

it('refuses to remove when the tenant disables it', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();
    $member = User::factory()->asMemberOf($organization)->create();

    app(ToggleFeature::class)->deactivate($organization, Feature::MemberDeletion);

    expect(app(RemoveMember::class)->handle($organization, $member->person, $owner))->toBeFalse()
        ->and($organization->members()->whereKey($member->person->getKey())->exists())->toBeTrue();
});

it('keeps an owner membership outside its active window', function (array $dates) {
    $organization = Organization::factory()->create();
    $owner = User::factory()->asMemberOf($organization, Role::Owner)->create();

    $organization->members()->updateExistingPivot($owner->person->getKey(), $dates);

    $removed = app(RemoveMember::class)->handleMany(
        $organization,
        new Collection([$owner->person]),
    );

    expect($removed)->toBeEmpty()
        ->and($organization->members()->whereKey($owner->person->getKey())->exists())->toBeTrue();
})->with([
    'scheduled' => [['start_date' => now()->addDay()->toDateString(), 'finish_date' => null]],
    'expired' => [['start_date' => now()->subDays(10)->toDateString(), 'finish_date' => now()->subDay()->toDateString()]],
]);
