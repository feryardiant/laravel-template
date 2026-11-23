<?php

declare(strict_types=1);

use App\Actions\Memberships\CountNewMembers;
use App\Actions\Memberships\ListRecentMembers;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('counts and lists members inside the range', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->asMemberOf($organization)->create();

    $range = [
        'start' => today()->subDay()->toDateString(),
        'end' => today()->addDay()->toDateString(),
    ];

    expect(app(CountNewMembers::class)->handle($organization, $range))->toBe(1)
        ->and(app(ListRecentMembers::class)->handle($organization, $range)->first()?->is($member->person))->toBeTrue();
});
