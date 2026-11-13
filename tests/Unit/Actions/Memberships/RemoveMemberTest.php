<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Actions\Memberships\RemoveMember;
use App\Events\MemberRemoved;
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

    app(RemoveMember::class)->handleMany(
        $organization,
        new Collection([$lastMember->person, $sharedMember->person]),
    );

    expect($organization->members()->count())->toBe(0);

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

    app(RemoveMember::class)->handleMany(Organization::factory()->create(), new Collection);

    Event::assertNotDispatched(MemberRemoved::class);
});
