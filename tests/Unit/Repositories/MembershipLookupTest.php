<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Repositories\MembershipRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('finds a member inside the organization', function () {
    $organization = Organization::factory()->create();
    $person = Person::factory()->withoutCredential()->create();
    app(CreateMember::class)->attach($organization, $person, startDate: now(), role: Role::Member);

    $member = app(MembershipRepository::class)->findInOrganization($organization, $person->getKey());

    expect($member?->is($person))->toBeTrue()
        ->and($member?->pivot)->not->toBeNull();
});

it('does not find a person outside the organization', function () {
    $organization = Organization::factory()->create();
    $person = Person::factory()->withoutCredential()->create();

    expect(app(MembershipRepository::class)->findInOrganization($organization, $person->getKey()))
        ->toBeNull();
});

it('resolves only members of the organization in bulk', function () {
    $organization = Organization::factory()->create();
    $inside = Person::factory()->withoutCredential()->create();
    $outside = Person::factory()->withoutCredential()->create();
    app(CreateMember::class)->attach($organization, $inside, startDate: now());

    $members = app(MembershipRepository::class)->getInOrganization(
        $organization,
        [$inside->getKey(), $outside->getKey()],
    );

    expect($members->pluck('id')->all())->toBe([$inside->getKey()]);
});
