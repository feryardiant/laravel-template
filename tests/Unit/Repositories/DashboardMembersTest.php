<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Repositories\MembershipRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('counts and lists members inside the range', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->asMemberOf($organization)->create();

    $range = [
        'start' => today()->subDay()->toDateString(),
        'end' => today()->addDay()->toDateString(),
    ];

    $members = app(MembershipRepository::class);

    expect($members->countRecent($organization, $range))->toBe(1)
        ->and($members->getRecent($organization, $range)->first()?->is($member->person))->toBeTrue();
});
